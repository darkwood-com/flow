<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Revolt\EventLoop;
use RuntimeException;
use Throwable;

use function Amp\delay;
use function function_exists;

class AmpDriver implements DriverInterface
{
    private int $counter = 0;

    public function __construct()
    {
        if (!function_exists('Amp\\async')) {
            throw new RuntimeException('Amp is not loaded. Suggest install it with composer require amphp/amp');
        }
    }

    public function async(Closure $callback, Closure $onResolved = null): Closure
    {
        return function (...$args) use ($callback, $onResolved): void {
            EventLoop::queue(function (Closure $callback, array $args, Closure $onResolved = null) {
                try {
                    $callback(...$args, ...($args = []));
                    if ($onResolved) {
                        $onResolved(null);
                    }
                } catch (Throwable $exception) {
                    if ($onResolved) {
                        $onResolved($exception);
                    }
                } finally {
                    $this->pop();
                }
            }, $callback, $args, $onResolved);
            $this->push();
        };
    }

    public function delay(float $seconds): void
    {
        delay($seconds);
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
