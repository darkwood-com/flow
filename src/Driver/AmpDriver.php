<?php

declare(strict_types=1);

namespace Flow\Driver;

use Amp\DeferredFuture;
use Amp\Future;
use Closure;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\PullEvent;
use Flow\Exception\RuntimeException;
use Flow\JobInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver;
use RuntimeException as NativeRuntimeException;
use Throwable;

use function Amp\async;
use function Amp\delay;
use function function_exists;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class AmpDriver implements DriverInterface
{
    use DriverTrait;

    private int $ticks = 0;

    public function __construct(?Driver $driver = null)
    {
        if (!function_exists('Amp\async')) {
            throw new NativeRuntimeException('Amp is not loaded. Suggest install it with composer require amphp/amp.');
        }

        if ($driver !== null) {
            EventLoop::setDriver($driver);
        }
    }

    /**
     * @return Closure(TArgs): Future<TReturn>
     */
    public function async(Closure|JobInterface $callback): Closure
    {
        return static function (...$args) use ($callback) {
            return async(static function (Closure|JobInterface $callback, array $args) {
                try {
                    return $callback(...$args, ...($args = []));
                } catch (Throwable $exception) {
                    return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
                }
            }, $callback, $args);
        };
    }

    /**
     * @return Future<TReturn>
     */
    public function defer(Closure $callback): Future
    {
        $deferred = new DeferredFuture();

        EventLoop::queue(static function () use ($callback, $deferred): void {
            try {
                $callback(static function ($return) use ($deferred): void {
                    $deferred->complete($return);
                }, static function ($fn, $next): void {
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
        $inFlight = 0;
        $async = function (Closure|JobInterface $job) use (&$inFlight, &$stream) {
            return function (mixed $data) use ($job, &$inFlight, &$stream) {
                $async = $this->async($job);

                $inFlight++;
                $future = $async($data);

                return static function (Closure $map) use ($future, &$inFlight, &$stream): void {
                    $future->map(static function (mixed $value) use ($map): null {
                        $map($value);

                        return null;
                    })->catch(static function (Throwable $exception) use (&$stream): void {
                        ($stream['reportFailure'])($exception);
                    })->finally(static function () use (&$inFlight): void {
                        $inFlight--;
                    })->ignore();
                };
            };
        };

        $defer = function (Closure|JobInterface $job) use (&$inFlight, &$stream) {
            return function (Closure $map) use ($job, &$inFlight, &$stream): void {
                $inFlight++;
                $future = $this->defer($job);
                $future->map(static function (mixed $value) use ($map): null {
                    $map($value);

                    return null;
                })->catch(static function (Throwable $exception) use (&$stream): void {
                    ($stream['reportFailure'])($exception);
                })->finally(static function () use (&$inFlight): void {
                    $inFlight--;
                })->ignore();
            };
        };

        $loop = function () use (&$loop, &$stream, $async, $defer, &$inFlight): void {
            if (!($stream['hasFailure'])()) {
                foreach ($stream['dispatchers'] as $index => $dispatcher) {
                    try {
                        $nextIps = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIps();
                    } catch (Throwable $exception) {
                        ($stream['reportFailure'])($exception);

                        break;
                    }

                    foreach ($nextIps as $nextIp) {
                        $job = $stream['fnFlows'][$index]['job'];
                        $event = $this->createAsyncEvent($stream, $async, $defer, $job, $nextIp, $index);
                        $this->dispatchAsyncEvent($stream['dispatchers'][$index], $event);
                    }
                }
            }

            if (($stream['hasFailure'])() && $inFlight === 0) {
                EventLoop::getDriver()->stop();
            } elseif ($inFlight > 0 || $this->countIps($stream['dispatchers']) > 0 || $this->ticks > 0) {
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

    public function tick(float $interval, Closure $callback): Closure
    {
        $this->ticks++;
        $tickId = EventLoop::repeat($interval, $callback);

        return function () use ($tickId): void {
            EventLoop::cancel($tickId);
            $this->ticks--;
        };
    }
}
