<?php

declare(strict_types=1);

declare(ticks=1000);

namespace Flow\Driver;

use Closure;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Flow\Event\PullEvent;
use Flow\Exception\LogicException;
use Flow\Exception\RuntimeException;
use Flow\Ip;
use Flow\JobInterface;
use Generator;
use Throwable;

use function count;
use function is_array;
use function is_resource;
use function max;
use function hrtime;
use function spl_object_id;
use function usleep;

/** @internal Cooperative generator scheduler shared by the native stream drivers. */
trait StreamDriverTrait
{
    use DriverTrait;

    private const TOKEN_STREAM = 'stream';

    private const TOKEN_DELAY = 'delay';

    /**
     * @var array<int, array{interval: float, next: float, callback: Closure}>
     */
    private array $ticks = [];

    private int $nextTickId = 0;

    /**
     * @var array<int, array{
     *     generator: ?Generator,
     *     result: mixed,
     *     done: bool,
     *     sendValue: mixed,
     *     beforeFirstYield: bool,
     *     parked: bool,
     *     event: ?AsyncEvent<mixed>,
     *     next: Closure
     * }>
     */
    private array $tasks = [];

    /**
     * @var array<int, array{taskKey: int, stream: resource, mode: 'read'|'write', deadline: ?float}>
     */
    private array $streamWaits = [];

    /**
     * @var array<int, array{taskKey: int, until: float}>
     */
    private array $delayWaits = [];

    public function async(Closure|JobInterface $callback): Closure
    {
        return static function (...$args) use ($callback) {
            try {
                return $callback(...$args);
            } catch (Throwable $exception) {
                return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
            }
        };
    }

    public function defer(Closure $callback): mixed
    {
        $result = null;

        try {
            $callback(static function ($value) use (&$result): void {
                $result = $value;
            }, static function ($fn, $next): void {
                $fn($next);
            });
        } catch (Throwable $exception) {
            return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return $result;
    }

    /**
     * Yieldable: park until $stream is readable or $timeoutSeconds elapses.
     *
     * Usage: `$ready = (yield $driver->waitReadable($stream, $timeout));`
     *
     * @param resource $stream
     *
     * @return array{type: self::TOKEN_STREAM, stream: resource, mode: 'read', deadline: ?float}
     */
    public function waitReadable($stream, ?float $timeoutSeconds = null): array
    {
        return $this->createStreamWait($stream, 'read', $timeoutSeconds);
    }

    /**
     * Yieldable: park until $stream is writable or $timeoutSeconds elapses.
     *
     * Usage: `$ready = (yield $driver->waitWritable($stream, $timeout));`
     *
     * @param resource $stream
     *
     * @return array{type: self::TOKEN_STREAM, stream: resource, mode: 'write', deadline: ?float}
     */
    public function waitWritable($stream, ?float $timeoutSeconds = null): array
    {
        return $this->createStreamWait($stream, 'write', $timeoutSeconds);
    }

    /**
     * Yieldable delay. Prefer this from Generator jobs instead of delay().
     *
     * Usage: `yield $driver->waitDelay(0.05);`
     *
     * @return array{type: self::TOKEN_DELAY, until: float}
     */
    public function waitDelay(float $seconds): array
    {
        return [
            'type' => self::TOKEN_DELAY,
            'until' => (hrtime(true) / 1_000_000_000) + $seconds,
        ];
    }

    public function await(array &$stream): void
    {
        $async = function (bool $isTick, ?AsyncEvent &$event = null) {
            return function (Closure|JobInterface $job) use ($isTick, &$event) {
                return function (mixed $data) use ($isTick, $job, &$event) {
                    $run = $this->async($job);
                    $result = $run($data);

                    $next = static function ($return): void {};

                    $taskKey = count($this->tasks) > 0 ? max(array_keys($this->tasks)) + 1 : 0;
                    $isGenerator = $result instanceof Generator;
                    $this->tasks[$taskKey] = [
                        'generator' => $isGenerator ? $result : null,
                        'result' => $isGenerator ? null : $result,
                        'done' => !$isGenerator,
                        'sendValue' => null,
                        'beforeFirstYield' => true,
                        'parked' => false,
                        'event' => $event,
                        'next' => static function ($return) use (&$next): void {
                            $next($return); // @phpstan-ignore expr.resultUnused
                        },
                    ];

                    if ($isGenerator) {
                        $this->advanceTask($taskKey);
                    }

                    return static function (Closure $callback) use ($isTick, &$next): void {
                        if ($isTick === false) {
                            $next = static function ($return) use ($callback): void {
                                $callback($return);
                            };
                        }
                    };
                };
            };
        };

        $defer = function ($isTick) {
            return function (Closure|JobInterface $job) use ($isTick) {
                return function (Closure $next) use ($isTick, $job): void {
                    $result = $this->defer($job);
                    if ($isTick === false) {
                        $next($result);
                    }
                };
            };
        };

        do {
            $now = hrtime(true) / 1_000_000_000;
            foreach (array_keys($this->ticks) as $id) {
                $this->dispatchTick($id, $now, $async);
            }

            foreach ($stream['dispatchers'] as $index => $dispatcher) {
                $nextIps = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIps();
                foreach ($nextIps as $nextIp) {
                    unset($event);
                    $event = $this->createAsyncEvent($stream, static function (Closure|JobInterface $job) use ($async, &$event) {
                        return $async(false, $event)($job);
                    }, static function (Closure|JobInterface $job) use ($defer) {
                        return $defer(false)($job);
                    }, $stream['fnFlows'][$index]['job'], $nextIp, $index);
                    $this->dispatchAsyncEvent($stream['dispatchers'][$index], $event);
                }
            }

            $this->resumeDelays();
            $this->resumeStreamWaits($stream);

            foreach (array_keys($this->tasks) as $taskKey) {
                $current = $this->tasks[$taskKey];
                if (!$current['done'] && $current['generator'] !== null && !$current['parked']) {
                    $this->advanceTask($taskKey);
                }

                if ($this->tasks[$taskKey]['done']) {
                    ($this->tasks[$taskKey]['next'])($this->tasks[$taskKey]['result']);
                    $this->clearWaitsForTask($taskKey);
                    unset($this->tasks[$taskKey]);
                }
            }

            if ($this->tasks !== [] && $this->streamWaits === [] && $this->delayWaits === [] && $this->ticks === []) {
                // Runnable generators or sync completions — avoid busy spin
                usleep(1000);
            }
        } while ($this->countIps($stream['dispatchers']) > 0 || count($this->ticks) > 0 || $this->tasks !== []);
    }

    /**
     * Interface delay — cannot suspend a Generator mid-call without Fibers.
     * From Generator jobs use: yield $driver->waitDelay($seconds).
     */
    public function delay(float $seconds): void
    {
        throw new LogicException(
            static::class . '::delay() cannot suspend without Fibers. From a Generator job use: yield $driver->waitDelay($seconds)'
        );
    }

    public function tick(float $interval, Closure $callback): Closure
    {
        if (!is_finite($interval) || $interval <= 0.0) {
            throw new LogicException('The tick interval must be positive and finite.');
        }

        $i = $this->nextTickId++;
        $this->ticks[$i] = [
            'interval' => $interval,
            'next' => (hrtime(true) / 1_000_000_000) + $interval,
            'callback' => $callback,
        ];

        return function () use ($i): void {
            unset($this->ticks[$i]);
        };
    }

    private function dispatchTick(int $id, float $now, Closure $async): void
    {
        // Another callback may have cancelled this tick during the same turn.
        $tick = $this->ticks[$id] ?? null;
        if ($tick !== null && $tick['next'] <= $now) {
            $this->ticks[$id]['next'] = $now + $tick['interval'];
            $async(true)($tick['callback'])(null);
        }
    }

    /**
     * @param resource       $stream
     * @param 'read'|'write' $mode
     *
     * @return array{type: self::TOKEN_STREAM, stream: resource, mode: 'read'|'write', deadline: ?float}
     */
    private function createStreamWait($stream, string $mode, ?float $timeoutSeconds): array
    {
        if (!is_resource($stream)) {
            throw new LogicException('waitReadable/waitWritable expects an open stream resource.');
        }

        return [
            'type' => self::TOKEN_STREAM,
            'stream' => $stream,
            'mode' => $mode,
            'deadline' => $timeoutSeconds === null ? null : (hrtime(true) / 1_000_000_000) + $timeoutSeconds,
        ];
    }

    private function isStreamWait(mixed $value): bool
    {
        return is_array($value) && ($value['type'] ?? null) === self::TOKEN_STREAM;
    }

    private function isDelayWait(mixed $value): bool
    {
        return is_array($value) && ($value['type'] ?? null) === self::TOKEN_DELAY;
    }

    private function advanceTask(int $taskKey): void
    {
        if (!isset($this->tasks[$taskKey])) {
            return;
        }

        $task = &$this->tasks[$taskKey];
        $generator = $task['generator'];
        if ($generator === null || $task['parked']) {
            return;
        }

        try {
            if ($task['beforeFirstYield']) {
                $task['beforeFirstYield'] = false;
                if (!$generator->valid()) {
                    $task['result'] = $generator->getReturn();
                    $task['generator'] = null;
                    $task['done'] = true;

                    return;
                }
                $value = $generator->current();
            } else {
                $value = $generator->send($task['sendValue']);
                $task['sendValue'] = null;
            }

            if (!$generator->valid()) {
                try {
                    $task['result'] = $generator->getReturn();
                } catch (Throwable) {
                    $task['result'] = $value;
                }
                $task['generator'] = null;
                $task['done'] = true;

                return;
            }

            if ($this->isStreamWait($value)) {
                $task['parked'] = true;
                $this->streamWaits[spl_object_id($generator)] = [
                    'taskKey' => $taskKey,
                    'stream' => $value['stream'],
                    'mode' => $value['mode'],
                    'deadline' => $value['deadline'],
                ];

                return;
            }

            if ($this->isDelayWait($value)) {
                $task['parked'] = true;
                $this->delayWaits[spl_object_id($generator)] = [
                    'taskKey' => $taskKey,
                    'until' => $value['until'],
                ];

                return;
            }

            // Plain yield — resume next loop tick with null
            $task['sendValue'] = null;
        } catch (Throwable $exception) {
            $task['result'] = new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
            $task['generator'] = null;
            $task['parked'] = false;
            $task['done'] = true;
            $this->clearWaitsForTask($taskKey);
        }
    }

    private function resumeDelays(): void
    {
        if ($this->delayWaits === []) {
            return;
        }

        $now = (hrtime(true) / 1_000_000_000);
        foreach ($this->delayWaits as $id => $wait) {
            if ($now < $wait['until']) {
                continue;
            }

            $taskKey = $wait['taskKey'];
            unset($this->delayWaits[$id]);
            if (!isset($this->tasks[$taskKey])) {
                continue;
            }

            $this->tasks[$taskKey]['parked'] = false;
            $this->tasks[$taskKey]['sendValue'] = null;
            $this->advanceTask($taskKey);
        }
    }

    /** @param array<mixed> $stream */
    private function resumeStreamWaits(array $stream): void
    {
        $now = (hrtime(true) / 1_000_000_000);
        $timeoutSeconds = null;

        foreach ($this->delayWaits as $wait) {
            $remaining = max(0.0, $wait['until'] - $now);
            $timeoutSeconds = min($timeoutSeconds ?? $remaining, $remaining);
        }

        foreach ($this->ticks as $tick) {
            $remaining = max(0.0, $tick['next'] - $now);
            $timeoutSeconds = min($timeoutSeconds ?? $remaining, $remaining);
        }

        $hasRunnableTasks = count($this->tasks) > count($this->streamWaits) + count($this->delayWaits);
        $hasQueuedIps = $this->countIps($stream['dispatchers']) > count($this->tasks);
        if ($hasRunnableTasks || $hasQueuedIps) {
            $slice = $hasRunnableTasks ? 0.001 : 0.02;
            $timeoutSeconds = min($timeoutSeconds ?? $slice, $slice);
        }

        if ($this->streamWaits === []) {
            if ($timeoutSeconds !== null) {
                usleep((int) clamp($timeoutSeconds * 1_000_000, 1, 200_000));
            }

            return;
        }

        $read = [];
        $write = [];

        foreach ($this->streamWaits as $id => $wait) {
            if ($wait['deadline'] !== null) {
                $remaining = $wait['deadline'] - $now;
                if ($remaining <= 0) {
                    unset($this->streamWaits[$id]);
                    $this->unparkTask($wait['taskKey'], false);
                    $timeoutSeconds = 0.0;

                    continue;
                }
                $timeoutSeconds = $timeoutSeconds === null ? $remaining : min($timeoutSeconds, $remaining);
            }

            if ($wait['mode'] === 'read') {
                $read[$id] = $wait['stream'];
            } else {
                $write[$id] = $wait['stream'];
            }
        }

        if ($read === [] && $write === []) {
            return;
        }

        try {
            [$readyRead, $readyWrite] = $this->pollStreams($read, $write, $timeoutSeconds);
        } catch (Throwable $exception) {
            foreach ($this->streamWaits as $id => $wait) {
                $taskKey = $wait['taskKey'];
                $event = $this->tasks[$taskKey]['event'];
                unset($this->streamWaits[$id], $this->tasks[$taskKey]);
                if ($event !== null) {
                    $event->fail($exception);
                }
            }
            ($stream['reportFailure'])($exception);

            return;
        }

        foreach ($this->streamWaits as $id => $wait) {
            if (!isset($read[$id]) && !isset($write[$id])) {
                continue;
            }

            $streamId = (int) $wait['stream'];
            $isReady = ($wait['mode'] === 'read' && isset($readyRead[$streamId]))
                || ($wait['mode'] === 'write' && isset($readyWrite[$streamId]));

            if ($isReady) {
                unset($this->streamWaits[$id]);
                $this->unparkTask($wait['taskKey'], true);
            }
        }
    }

    /**
     * @param array<int, resource> $read
     * @param array<int, resource> $write
     *
     * @return array{array<int, true>, array<int, true>} Ready stream IDs by direction
     */
    abstract private function pollStreams(array $read, array $write, ?float $timeoutSeconds): array;

    private function unparkTask(int $taskKey, mixed $sendValue): void
    {
        if (!isset($this->tasks[$taskKey])) {
            return;
        }

        $this->tasks[$taskKey]['parked'] = false;
        $this->tasks[$taskKey]['sendValue'] = $sendValue;
        $this->advanceTask($taskKey);
    }

    private function clearWaitsForTask(int $taskKey): void
    {
        foreach ($this->streamWaits as $id => $wait) {
            if ($wait['taskKey'] === $taskKey) {
                unset($this->streamWaits[$id]);
            }
        }
        foreach ($this->delayWaits as $id => $wait) {
            if ($wait['taskKey'] === $taskKey) {
                unset($this->delayWaits[$id]);
            }
        }
    }
}
