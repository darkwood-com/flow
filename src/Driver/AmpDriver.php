<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Exception;
use Revolt\EventLoop;
use RuntimeException;
use Throwable;

use function Amp\async;
use function Amp\delay;
use function function_exists;

class AmpDriver implements DriverInterface
{
    public function __construct()
    {
        if (!function_exists('Amp\\async')) {
            throw new RuntimeException('Amp is not loaded. Suggest install it with composer require amphp/amp');
        }
    }

    public function async(Closure $callback, Closure $onResolve = null): Closure
    {
        return static function (...$args) use ($callback, $onResolve): void {
            async(static function (Closure $callback, array $args, Closure $onResolve = null) {
                try {
                    $return = $callback(...$args, ...($args = []));
                    if ($onResolve) {
                        $onResolve($return);
                    }
                } catch (Throwable $exception) {
                    if ($onResolve) {
                        $onResolve(new Exception($exception->getMessage(), $exception->getCode(), $exception));
                    }
                }
            }, $callback, $args, $onResolve)->await();
        };
    }

    public function delay(float $seconds): void
    {
        delay($seconds);
    }

    public function tick(int $interval, Closure $callback): Closure
    {
        $tickId = EventLoop::repeat($interval / 1000, $callback);

        return static function () use ($tickId) {
            EventLoop::cancel($tickId);
        };
    }
}
