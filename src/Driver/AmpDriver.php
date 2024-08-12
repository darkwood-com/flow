<?php

declare(strict_types=1);

namespace Flow\Driver;

use Amp\DeferredFuture;
use Amp\Future;
use Closure;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Flow\Event\PopEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Exception\RuntimeException;
use Flow\Ip;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver;
use RuntimeException as NativeRuntimeException;
use Throwable;

use function Amp\async;
use function Amp\delay;
use function array_key_exists;
use function function_exists;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class AmpDriver implements DriverInterface
{
    private int $ticks = 0;

    public function __construct(?Driver $driver = null)
    {
        if (!function_exists('Amp\async')) {
            throw new NativeRuntimeException('Amp is not loaded. Suggest install it with composer require amphp/amp');
        }

        if ($driver !== null) {
            EventLoop::setDriver($driver);
        }
    }

    public function async(Closure $callback): Closure
    {
        return static function (...$args) use ($callback) {
            return async(static function (Closure $callback, array $args) {
                try {
                    return $callback(...$args, ...($args = []));
                } catch (Throwable $exception) {
                    dd($exception);

                    return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
                }
            }, $callback, $args);
        };
    }

    public function await(array &$stream): void
    {
        $loop = function () use (&$loop, &$stream) {
            $nextIp = null;
            do {
                foreach ($stream['dispatchers'] as $index => $dispatcher) {
                    $nextIp = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIp();
                    if ($nextIp !== null) {
                        $defer = function(Closure $job): Future
                        {
                            $deferred = new DeferredFuture();

                            // Queue the operation to be executed in the event loop
                            EventLoop::queue(static function () use ($job, $deferred) {
                                try {
                                    $job(static function ($return) use ($deferred) {
                                        $deferred->complete($return);
                                    }, static function (Future $future, $next) {
                                        $future->map($next);
                                    });
                                } catch (Throwable $exception) {
                                    dd($exception);
                                    $deferred->complete(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
                                }
                            });

                            return $deferred->getFuture();
                        };

                        $async = $stream['fnFlows'][$index]['job'];
                        $future = $async([$nextIp->data, $defer]);

                        $future->map(static function ($args) use (&$stream, $index, $nextIp) {
                            [$data, $defer] = $args;
                            if ($data instanceof RuntimeException and array_key_exists($index, $stream['fnFlows']) && $stream['fnFlows'][$index]['errorJob'] !== null) {
                                $stream['fnFlows'][$index]['errorJob']($data);
                            } elseif (array_key_exists($index + 1, $stream['fnFlows'])) {
                                $ip = new Ip($data);
                                $stream['ips']++;
                                $stream['dispatchers'][$index + 1]->dispatch(new PushEvent($ip), Event::PUSH);
                            }

                            $stream['dispatchers'][$index]->dispatch(new PopEvent($nextIp), Event::POP);
                            $stream['ips']--;
                        });
                    }
                }
            } while ($nextIp !== null);

            if ($stream['ips'] > 0 or $this->ticks > 0) {
                EventLoop::defer($loop);
            } else {
                EventLoop::getDriver()->stop();
            }
        };
        EventLoop::defer($loop);

        EventLoop::run();

        /*$async = function (Closure $wrapper, $ip, $fnFlows, $index, $map) {
            $async = $this->async($wrapper($fnFlows[$index]['job']));

            $wrap = function(Closure $job) {
                $deferred = new DeferredFuture();

                EventLoop::queue(function () use ($job, $deferred) {
                    $job(static function($value) use ($deferred) {
                        $deferred->complete($value);
                    }, static function(Future $future, $next) {
                        $future->map($next);
                    });
                });

                return $deferred->getFuture();
            };

            if ($ip->data === null) {
                $future = $async($wrap);
            } else {
                $future = $async($ip->data, $wrap);
            }

            $future->map($map);
        };

        $loop = function () use (&$loop, &$stream, $async) {
            $nextIp = null;
            do {
                foreach ($stream['dispatchers'] as $index => $dispatcher) {
                    $nextIp = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIp();
                    if ($nextIp !== null) {
                        $stream['dispatchers'][$index]->dispatch(new AsyncEvent($async, static function($job) {
                            return $job;
                        }, $nextIp, $stream['fnFlows'], $index, static function ($data) use (&$stream, $index, $nextIp) {
                            if ($data instanceof RuntimeException and array_key_exists($index, $stream['fnFlows']) && $stream['fnFlows'][$index]['errorJob'] !== null) {
                                $stream['fnFlows'][$index]['errorJob']($data);
                            } elseif (array_key_exists($index + 1, $stream['fnFlows'])) {
                                $ip = new Ip($data);
                                $stream['ips']++;
                                $stream['dispatchers'][$index + 1]->dispatch(new PushEvent($ip), Event::PUSH);
                            }

                            $stream['dispatchers'][$index]->dispatch(new PopEvent($nextIp), Event::POP);
                            $stream['ips']--;
                        }), Event::ASYNC);
                    }
                }
            } while ($nextIp !== null);

            if ($stream['ips'] > 0 or $this->ticks > 0) {
                EventLoop::defer($loop);
            } else {
                EventLoop::getDriver()->stop();
            }
        };
        EventLoop::defer($loop);

        EventLoop::run();*/
    }

    public function delay(float $seconds): void
    {
        delay($seconds);
    }

    public function tick($interval, Closure $callback): Closure
    {
        $this->ticks++;
        $tickId = EventLoop::repeat($interval, $callback);

        return function () use ($tickId) {
            EventLoop::cancel($tickId);
            $this->ticks--;
        };
    }
}
