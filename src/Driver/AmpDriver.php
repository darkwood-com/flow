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
                    return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
                }
            }, $callback, $args);
        };
    }

    public function defer(Closure $callback): Future
    {
        $deferred = new DeferredFuture();

        EventLoop::queue(static function () use ($callback, $deferred) {
            try {
                $callback(static function ($return) use ($deferred) {
                    $deferred->complete($return);
                }, static function ($fn, $next) {
                    $fn($next);
                });
            } catch (Throwable $exception) {
                $deferred->complete(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
            }
        });

        return $deferred->getFuture();
    }

    public function await(array &$stream): void
    {
        $async = function (Closure $job) {
            return function (mixed $data) use ($job) {
                $async = $this->async($job);

                if ($data === null) {
                    $future = $async();
                } else {
                    $future = $async($data);
                }

                return static function ($map) use ($future) {
                    $future->map($map);
                };
            };
        };

        $defer = function (Closure $job) {
            return function ($map) use ($job) {
                $future = $this->defer($job);
                $future->map($map);
            };
        };

        $loop = function () use (&$loop, &$stream, $async, $defer) {
            $nextIp = null;
            do {
                foreach ($stream['dispatchers'] as $index => $dispatcher) {
                    $nextIp = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIp();
                    if ($nextIp !== null) {
                        $job = $stream['fnFlows'][$index]['job'];

                        $stream['dispatchers'][$index]->dispatch(new AsyncEvent($async, $defer, $job, $nextIp, static function ($data) use (&$stream, $index, $nextIp) {
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

        EventLoop::run();
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
