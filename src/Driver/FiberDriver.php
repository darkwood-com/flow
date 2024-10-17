<?php

declare(strict_types=1);

declare(ticks=1000);

namespace Flow\Driver;

use Closure;
use Fiber;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Flow\Event\PopEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Exception\RuntimeException;
use Flow\Ip;
use Flow\JobInterface;
use Throwable;

use function array_key_exists;
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
        $fiber = new Fiber(static function () use ($callback) {
            try {
                $callback(static function ($result) {
                    Fiber::suspend($result);
                }, static function ($fn, $next) {
                    $fn($next);
                });
            } catch (Throwable $exception) {
                Fiber::suspend(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
            }
        });

        $fiber->start();

        return $fiber->resume();
    }

    public function await(array &$stream): void
    {
        $async = function ($isTick) use (&$fiberDatas) {
            return function (Closure|JobInterface $job) use (&$fiberDatas, $isTick) {
                return function (mixed $data) use (&$fiberDatas, $isTick, $job) {
                    $async = $this->async($job);

                    $fiber = $async($data);
                    $fiber->start();

                    $next = static function ($return) {};

                    $fiberDatas[] = [
                        'fiber' => $fiber,
                        'next' => static function ($return) use (&$next) {
                            $next($return);
                        },
                    ];

                    return static function (Closure $callback) use ($isTick, &$next) {
                        if ($isTick === false) {
                            $next = static function ($return) use ($callback) {
                                $callback($return);
                            };
                        }
                    };
                };
            };
        };

        $defer = static function ($isTick) use (&$fiberDatas) {
            return static function (Closure|JobInterface $job) use ($isTick, &$fiberDatas) {
                return static function (Closure $next) use ($isTick, $job, &$fiberDatas) {
                    $fiber = new Fiber(static function () use ($isTick, $job, $next) {
                        try {
                            $job(static function ($return) use ($isTick, $next) {
                                if ($isTick === false) {
                                    $next($return);
                                }
                            }, static function ($fn, $next) {
                                $fn($next);
                            });
                        } catch (Throwable $exception) {
                            return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
                        }
                    });

                    $fiber->start();

                    $fiberDatas[] = [
                        'fiber' => $fiber,
                        'next' => static function ($return) {}, /*function ($return) use ($isTick, $next) {
                            if ($isTick === false) {
                                $next($return);
                            }
                        },*/
                    ];
                };
            };
        };

        $tick = 0;
        $fiberDatas = [];
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
                    }, $stream['fnFlows'][$index]['job'], $nextIp, static function ($data) use (&$stream, $index, $nextIp) {
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

            foreach ($fiberDatas as $i => $fiberData) { // @phpstan-ignore-line see https://github.com/phpstan/phpstan/issues/11468
                if (!$fiberData['fiber']->isTerminated() and $fiberData['fiber']->isSuspended()) {
                    $fiberData['fiber']->resume();
                } else {
                    $data = $fiberData['fiber']->getReturn();
                    $fiberData['next']($data);
                    unset($fiberDatas[$i]);
                }
            }

            $tick++;
        } while ($this->countIps($stream['dispatchers']) > 0 or count($this->ticks) > 0);
    }

    public function delay(float $seconds): void
    {
        $date = time();
        do {
            Fiber::suspend();
        } while (time() - $date < $seconds);
    }

    public function tick($interval, Closure $callback): Closure
    {
        $i = count($this->ticks) - 1;
        $this->ticks[$i] = [
            'interval' => $interval,
            'callback' => $callback,
        ];

        return function () use ($i) {
            unset($this->ticks[$i]);
        };
    }
}
