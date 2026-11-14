<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\PullEvent;
use Flow\Exception\RuntimeException;
use Flow\JobInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\Promise;
use RuntimeException as NativeRuntimeException;
use Throwable;

use function function_exists;
use function React\Async\async;
use function React\Async\delay;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class ReactDriver implements DriverInterface
{
    use DriverTrait;

    private int $ticks = 0;

    private LoopInterface $eventLoop;

    public function __construct(?LoopInterface $eventLoop = null)
    {
        if (!function_exists('React\Async\async')) {
            throw new NativeRuntimeException('ReactPHP is not loaded. Suggest install it with composer require react/event-loop.');
        }

        $this->eventLoop = $eventLoop ?? Loop::get();
    }

    public function async(Closure|JobInterface $callback): Closure
    {
        return static function (...$args) use ($callback) {
            return async(static function () use ($callback, $args) {
                try {
                    return $callback(...$args, ...($args = []));
                } catch (Throwable $exception) {
                    return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
                }
            })();
        };
    }

    /**
     * @return Promise<TReturn>
     */
    public function defer(Closure $callback): Promise
    {
        $deferred = new Deferred();

        try {
            $callback(static function ($return) use ($deferred): void {
                $deferred->resolve($return);
            }, static function ($fn, $next): void {
                $fn($next);
            });
        } catch (Throwable $exception) {
            $deferred->resolve(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
        }

        return $deferred->promise();
    }

    public function await(array &$stream): void
    {
        $async = function (Closure|JobInterface $job) {
            return function (mixed $data) use ($job) {
                $async = $this->async($job);

                $promise = $async($data);

                return static function ($then) use ($promise): void {
                    $promise->then($then);
                };
            };
        };

        $defer = function (Closure|JobInterface $job) {
            return function ($then) use ($job): void {
                $promise = $this->defer($job);
                $promise->then($then);
            };
        };

        $loop = function () use (&$loop, &$stream, $async, $defer): void {
            foreach ($stream['dispatchers'] as $index => $dispatcher) {
                $nextIps = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIps();
                foreach ($nextIps as $nextIp) {
                    $job = $stream['fnFlows'][$index]['job'];

                    $event = $this->createAsyncEvent($stream, $async, $defer, $job, $nextIp, $index);
                    $this->dispatchAsyncEvent($stream['dispatchers'][$index], $event);
                }
            }

            if ($this->countIps($stream['dispatchers']) > 0 || $this->ticks > 0) {
                $this->eventLoop->futureTick($loop);
            } else {
                $this->eventLoop->stop();
            }
        };
        $this->eventLoop->futureTick($loop);

        $this->eventLoop->run();
    }

    public function delay(float $seconds): void
    {
        delay($seconds);
    }

    public function tick($interval, Closure $callback): Closure
    {
        $this->ticks++;
        $timer = $this->eventLoop->addPeriodicTimer($interval, $callback);

        return function () use ($timer): void {
            $this->eventLoop->cancelTimer($timer);
            $this->ticks--;
        };
    }
}
