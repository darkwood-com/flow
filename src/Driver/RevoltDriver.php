<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Exception\RuntimeException;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver;
use RuntimeException as NativeRuntimeException;
use Throwable;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class RevoltDriver implements DriverInterface
{
    private int $counter = 0;

    public function __construct(Driver $driver = null)
    {
        if (!class_exists('Revolt\\EventLoop')) {
            throw new NativeRuntimeException('Revolt is not loaded. Suggest install it with composer require revolt/event-loop');
        }

        if ($driver !== null) {
            EventLoop::setDriver($driver);
        }
    }

    public function async(Closure $callback, Closure $onResolve = null): Closure
    {
        return function (...$args) use ($callback, $onResolve): void {
            EventLoop::queue(function (Closure $callback, array $args, Closure $onResolve = null) {
                try {
                    $return = $callback(...$args, ...($args = []));
                    if ($onResolve) {
                        $onResolve($return);
                    }
                } catch (Throwable $exception) {
                    if ($onResolve) {
                        $onResolve(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
                    }
                } finally {
                    $this->pop();
                }
            }, $callback, $args, $onResolve);
            $this->push();
        };
    }

    public function delay(float $seconds): void
    {
        $suspension = EventLoop::getSuspension();
        $callbackId = EventLoop::delay($seconds, static fn () => $suspension->resume());

        try {
            $suspension->suspend();
        } finally {
            EventLoop::cancel($callbackId);
        }
    }

    public function tick(int $interval, Closure $callback): Closure
    {
        $tickId = EventLoop::repeat($interval / 1000, $callback);
        $this->push();

        return function () use ($tickId) {
            EventLoop::cancel($tickId);
            $this->pop();
        };
    }

    private function push(): void
    {
        if (/* $this->counter === 0 || */ !EventLoop::getDriver()->isRunning()) {
            EventLoop::run();
        }
        $this->counter++;
    }

    private function pop(): void
    {
        $this->counter--;
        if ($this->counter === 0) {
            EventLoop::getDriver()->stop();
        }
    }
}
