<?php

declare(strict_types=1);

declare(ticks=1000);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\PullEvent;
use Flow\Exception\RuntimeException;
use Flow\JobInterface;
use RuntimeException as NativeRuntimeException;
use Spatie\Async\Pool;
use Throwable;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class SpatieDriver implements DriverInterface
{
    use DriverTrait;

    private int $ticks = 0;

    private Pool $pool;

    public function __construct()
    {
        if (!class_exists('Spatie\Async\Pool')) {
            throw new NativeRuntimeException('Spatie Async is not loaded. Suggest install it with composer require spatie/async.');
        }

        $this->pool = Pool::create();
        if (!$this->pool->isSupported()) {
            throw new NativeRuntimeException('Spatie Async will not run asynchronously. PHP pcntl and posix extension are required.');
        }
    }

    public function async(Closure|JobInterface $callback): Closure
    {
        return function (...$args) use ($callback) {
            return function ($onResolve) use ($callback, $args): void {
                $this->pool->add(static function () use ($callback, $args) {
                    return $callback(...$args, ...($args = []));
                })->then(static function ($return) use ($onResolve): void {
                    $onResolve($return);
                })->catch(static function (Throwable $exception) use ($onResolve): void {
                    $onResolve(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
                });
            };
        };
    }

    public function defer(Closure $callback): mixed
    {
        return null;
    }

    public function await(array &$stream): void
    {
        $async = function (Closure|JobInterface $job) {
            return function (mixed $data) use ($job) {
                $async = $this->async($job);

                return $async($data);
            };
        };

        $defer = function (Closure|JobInterface $job) {
            return function (Closure $onResolve) use ($job): void {
                $this->pool->add(static function () use ($job, $onResolve) {
                    return $job($onResolve, static function ($fn, $next): void {
                        $fn($next);
                    });
                })->catch(static function (Throwable $exception) use ($onResolve): void {
                    $onResolve(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
                });
            };
        };

        do {
            foreach ($stream['dispatchers'] as $index => $dispatcher) {
                $nextIps = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIps();
                foreach ($nextIps as $nextIp) {
                    $event = $this->createAsyncEvent($stream, $async, $defer, $stream['fnFlows'][$index]['job'], $nextIp, $index);
                    $this->dispatchAsyncEvent($stream['dispatchers'][$index], $event);
                }
            }
        } while ($this->countIps($stream['dispatchers']) > 0 or $this->ticks > 0);
    }

    public function delay(float $seconds): void
    {
        sleep((int) $seconds);
    }

    public function tick($interval, Closure $callback): Closure
    {
        $this->ticks++;
        $closure = static fn () => $callback();
        register_tick_function($closure);

        return function () use ($closure): void {
            unregister_tick_function($closure);
            $this->ticks--;
        };
    }
}
