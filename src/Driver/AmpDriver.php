<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Exception;
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
                        $onResolve(new Exception($exception->getMessage(), $exception->getCode(), $exception));
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
