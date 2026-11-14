<?php

declare(strict_types=1);

declare(ticks=1000);

namespace Flow\Driver;

use Closure;
use Fiber;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\PullEvent;
use Flow\Exception\RuntimeException;
use Flow\Ip;
use Flow\JobInterface;
use InvalidArgumentException;
use Throwable;

use function count;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class FiberDriver implements DriverInterface
{
    use DriverTrait;

    /**
     * @var array<mixed>
     */
    private array $ticks = [];

    private int $nextTickId = 0;

    public function async(Closure|JobInterface $callback): Closure
    {
        return static function (...$args) use ($callback) {
            return new Fiber(static function () use ($callback, $args) {
                try {
                    return $callback(...$args);
                } catch (Throwable $exception) {
                    return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
                }
            });
        };
    }

    public function defer(Closure $callback): mixed
    {
        $fiber = new Fiber(static function () use ($callback): void {
            try {
                $callback(static function ($result): void {
                    Fiber::suspend($result);
                }, static function ($fn, $next): void {
                    $fn($next);
                });
            } catch (Throwable $exception) {
                Fiber::suspend(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
            }
        });

        return $fiber->start();
    }

    public function await(array &$stream): void
    {
        $hasFailure = $stream['hasFailure'] ?? static fn (): bool => false;
        $reportFailure = $stream['reportFailure'] ?? static function (Throwable $exception): never {
            throw $exception;
        };

        $async = function ($isTick) use (&$fiberDatas) {
            return function (Closure|JobInterface $job) use (&$fiberDatas, $isTick) {
                return function (mixed $data) use (&$fiberDatas, $isTick, $job) {
                    $async = $this->async($job);

                    $fiber = $async($data);
                    $fiber->start();

                    $next = static function ($return): void {};

                    $fiberDatas[] = [
                        'fiber' => $fiber,
                        'next' => static function ($return) use (&$next): void {
                            $next($return); // @phpstan-ignore expr.resultUnused
                        },
                    ];

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

        $defer = static function ($isTick) use (&$fiberDatas) {
            return static function (Closure|JobInterface $job) use ($isTick, &$fiberDatas) {
                return static function (Closure $next) use ($isTick, $job, &$fiberDatas): void {
                    $fiber = new Fiber(static function () use ($isTick, $job, $next) {
                        try {
                            $job(static function ($return) use ($isTick, $next): void {
                                if ($isTick === false) {
                                    $next($return);
                                }
                            }, static function ($fn, $next): void {
                                $fn($next);
                            });
                        } catch (Throwable $exception) {
                            return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
                        }
                    });

                    $fiber->start();

                    $fiberDatas[] = [
                        'fiber' => $fiber,
                        'next' => static function ($return): void {}, /*function ($return) use ($isTick, $next) {
                            if ($isTick === false) {
                                $next($return);
                            }
                        },*/
                    ];
                };
            };
        };

        $fiberDatas = [];
        do {
            $now = $this->monotonicTime();
            foreach ($this->ticks as $id => [
                'interval' => $interval,
                'nextRun' => $nextRun,
                'callback' => $callback,
            ]) {
                if ($hasFailure() || $now < $nextRun) {
                    continue;
                }

                do {
                    $nextRun += $interval;
                } while ($nextRun <= $now);
                $this->ticks[$id]['nextRun'] = $nextRun;

                $ip = new Ip();
                $async(true)($callback)($ip->data);
            }

            if (!$hasFailure()) {
                foreach ($stream['dispatchers'] as $index => $dispatcher) {
                    try {
                        $nextIps = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIps();
                    } catch (Throwable $exception) {
                        $reportFailure($exception);

                        break;
                    }

                    foreach ($nextIps as $nextIp) {
                        $event = $this->createAsyncEvent($stream, static function (Closure|JobInterface $job) use ($async) {
                            return $async(false)($job);
                        }, static function (Closure|JobInterface $job) use ($defer) {
                            return $defer(false)($job);
                        }, $stream['fnFlows'][$index]['job'], $nextIp, $index);
                        $this->dispatchAsyncEvent($stream['dispatchers'][$index], $event);
                    }
                }
            }

            foreach ($fiberDatas as $i => $fiberData) { // @phpstan-ignore-line see https://github.com/phpstan/phpstan/issues/11468
                try {
                    if (!$fiberData['fiber']->isTerminated() and $fiberData['fiber']->isSuspended()) {
                        $fiberData['fiber']->resume();
                    } else {
                        $data = $fiberData['fiber']->getReturn();
                        $fiberData['next']($data);
                        unset($fiberDatas[$i]);
                    }
                } catch (Throwable $exception) {
                    $reportFailure($exception);
                    unset($fiberDatas[$i]);
                }
            }

            if ($hasFailure()) {
                if (!$this->hasPendingFibers($fiberDatas)) {
                    break;
                }

                continue;
            }
        } while ($this->countIps($stream['dispatchers']) > 0 or count($this->ticks) > 0);
    }

    public function delay(float $seconds): void
    {
        $deadline = $this->monotonicTime() + $seconds;
        while ($this->monotonicTime() < $deadline) {
            Fiber::suspend();
        }
    }

    public function tick(float $interval, Closure $callback): Closure
    {
        if ($interval <= 0) {
            throw new InvalidArgumentException('The timer interval must be greater than zero.');
        }

        $i = $this->nextTickId++;
        $this->ticks[$i] = [
            'interval' => $interval,
            'nextRun' => $this->monotonicTime() + $interval,
            'callback' => $callback,
        ];
        $cancelled = false;

        return function () use ($i, &$cancelled): void {
            if ($cancelled) {
                return;
            }

            $cancelled = true;
            unset($this->ticks[$i]);
        };
    }

    private function monotonicTime(): float
    {
        return hrtime(true) / 1_000_000_000;
    }

    /**
     * @param array<mixed> $fiberDatas
     */
    private function hasPendingFibers(array $fiberDatas): bool
    {
        return [] !== $fiberDatas;
    }
}
