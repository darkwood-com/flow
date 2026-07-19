<?php

declare(strict_types=1);

declare(ticks=1000);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Flow\Event\PopEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Exception\LogicException;
use Flow\Exception\RuntimeException;
use Flow\Ip;
use Flow\JobInterface;
use Generator;
use Throwable;

use function array_key_exists;
use function count;
use function is_array;
use function is_resource;
use function max;
use function microtime;
use function spl_object_id;
use function usleep;

/**
 * Experimental Driver: cooperative Generators + native stream_select().
 *
 * PHP Streams (stream_set_blocking / stream_select) are the I/O multiplexer.
 * Generators provide cooperative suspension — Jobs must yield wait tokens:
 *
 *   $ready = (yield $driver->waitReadable($stream, 5.0));
 *   yield $driver->waitDelay(0.05);
 *
 * Not an HTTP client. FiberDriver remains the default. Unreleased / experimental:
 * future PHP stream polling (epoll/kqueue) may replace the mux without changing
 * the wait-token API.
 *
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
final class StreamSelectDriver implements DriverInterface
{
    use DriverTrait;

    private const TOKEN_STREAM = 'stream';

    private const TOKEN_DELAY = 'delay';

    /**
     * @var array<int, array{interval: float|int, callback: Closure}>
     */
    private array $ticks = [];

    /**
     * @var array<int, array{
     *     generator: ?Generator,
     *     result: mixed,
     *     done: bool,
     *     sendValue: mixed,
     *     beforeFirstYield: bool,
     *     parked: bool,
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
            'until' => microtime(true) + $seconds,
        ];
    }

    public function await(array &$stream): void
    {
        $async = function ($isTick) {
            return function (Closure|JobInterface $job) use ($isTick) {
                return function (mixed $data) use ($isTick, $job) {
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

        $tick = 0;
        do {
            foreach ($this->ticks as [
                'interval' => $interval,
                'callback' => $callback,
            ]) {
                if ($tick % $interval === 0) {
                    $ip = new Ip();
                    $async(true)($callback)($ip->data);
                }
            }

            foreach ($stream['dispatchers'] as $index => $dispatcher) {
                $nextIps = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIps();
                foreach ($nextIps as $nextIp) {
                    $stream['dispatchers'][$index]->dispatch(new AsyncEvent(static function (Closure|JobInterface $job) use ($async) {
                        return $async(false)($job);
                    }, static function (Closure|JobInterface $job) use ($defer) {
                        return $defer(false)($job);
                    }, $stream['fnFlows'][$index]['job'], $nextIp, static function ($data) use (&$stream, $index, $nextIp): void {
                        if ($data instanceof RuntimeException && array_key_exists($index, $stream['fnFlows']) && $stream['fnFlows'][$index]['errorJob'] !== null) {
                            $stream['fnFlows'][$index]['errorJob']($data);
                        } elseif (array_key_exists($index + 1, $stream['fnFlows'])) {
                            $ip = new Ip($data);
                            $stream['dispatchers'][$index + 1]->dispatch(new PushEvent($ip), Event::PUSH);
                        }

                        $stream['dispatchers'][$index]->dispatch(new PopEvent($nextIp), Event::POP);
                    }), Event::ASYNC);
                }
            }

            $this->resumeDelays();
            $this->resumeStreamWaits();

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

            if ($this->tasks !== [] && $this->streamWaits === [] && $this->delayWaits === []) {
                // Runnable generators or sync completions — avoid busy spin
                usleep(1000);
            }

            $tick++;
        } while ($this->countIps($stream['dispatchers']) > 0 || count($this->ticks) > 0 || $this->tasks !== []);
    }

    /**
     * Interface delay — cannot suspend a Generator mid-call without Fibers.
     * From Generator jobs use: yield $driver->waitDelay($seconds).
     */
    public function delay(float $seconds): void
    {
        throw new LogicException(
            'StreamSelectDriver::delay() cannot suspend without Fibers. From a Generator job use: yield $driver->waitDelay($seconds)'
        );
    }

    public function tick($interval, Closure $callback): Closure
    {
        $i = count($this->ticks);
        $this->ticks[$i] = [
            'interval' => $interval,
            'callback' => $callback,
        ];

        return function () use ($i): void {
            unset($this->ticks[$i]);
        };
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
            'deadline' => $timeoutSeconds === null ? null : microtime(true) + $timeoutSeconds,
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

        $now = microtime(true);
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

    private function resumeStreamWaits(): void
    {
        $now = microtime(true);
        $timeoutSeconds = null;

        foreach ($this->delayWaits as $wait) {
            $remaining = $wait['until'] - $now;
            if ($remaining > 0) {
                $timeoutSeconds = $timeoutSeconds === null ? $remaining : min($timeoutSeconds, $remaining);
            }
        }

        if ($this->streamWaits === []) {
            if ($timeoutSeconds !== null) {
                usleep((int) min(200_000, max(1, (int) ($timeoutSeconds * 1_000_000))));
            }

            return;
        }

        $read = [];
        $write = [];
        $except = [];

        foreach ($this->streamWaits as $id => $wait) {
            if ($wait['deadline'] !== null) {
                $remaining = $wait['deadline'] - $now;
                if ($remaining <= 0) {
                    $this->unparkTask($wait['taskKey'], false);
                    unset($this->streamWaits[$id]);

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

        if ($this->streamWaits === [] || ($read === [] && $write === [])) {
            return;
        }

        $selectRead = array_values($read);
        $selectWrite = array_values($write);
        // Short slice so await() can Pull more IPs / advance other tasks; cap by nearest deadline.
        $slice = 0.02;
        if ($timeoutSeconds !== null) {
            $slice = min($slice, max(0.000001, $timeoutSeconds));
        }
        $seconds = (int) $slice;
        $microseconds = (int) (($slice - $seconds) * 1_000_000);
        if ($seconds === 0 && $microseconds === 0) {
            $microseconds = 1;
        }

        $selected = @stream_select($selectRead, $selectWrite, $except, $seconds, $microseconds);
        if ($selected === false || $selected === 0) {
            return;
        }

        $readyRead = [];
        foreach ($selectRead as $streamResource) {
            $readyRead[(int) $streamResource] = true;
        }
        $readyWrite = [];
        foreach ($selectWrite as $streamResource) {
            $readyWrite[(int) $streamResource] = true;
        }

        foreach ($this->streamWaits as $id => $wait) {
            $streamId = (int) $wait['stream'];
            $isReady = ($wait['mode'] === 'read' && isset($readyRead[$streamId]))
                || ($wait['mode'] === 'write' && isset($readyWrite[$streamId]));

            if ($isReady) {
                unset($this->streamWaits[$id]);
                $this->unparkTask($wait['taskKey'], true);
            }
        }
    }

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
