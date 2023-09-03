<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Exception;
use OpenSwoole\Coroutine;
use OpenSwoole\Timer;
use RuntimeException;
use Throwable;

use function extension_loaded;

class SwooleDriver implements DriverInterface
{
    public function __construct()
    {
        if (!extension_loaded('openswoole')) {
            throw new RuntimeException('Swoole extension is not loaded. Suggest install it with pecl install openswoole');
        }
    }

    public function async(Closure $callback, Closure $onResolve = null): Closure
    {
        return function (...$args) use ($callback, $onResolve): void {
            Coroutine::run(static function () use ($callback, $onResolve, $args) {
                Coroutine::create(static function (Closure $callback, array $args, Closure $onResolve = null) {
                    try {
                        $callback(...$args, ...($args = []));
                        if ($onResolve) {
                            $onResolve(null);
                        }
                    } catch (Throwable $exception) {
                        if ($onResolve) {
                            $onResolve(new Exception($exception->getMessage(), $exception->getCode(), $exception));
                        }
                    }
                }, $callback, $args, $onResolve);
            });
        };
    }

    public function delay(float $seconds): void
    {
        Coroutine::sleep((int) $seconds);
        // Coroutine::usleep((int) $seconds * 1000);
    }

    public function tick(int $interval, Closure $callback): Closure
    {
        $tickId = Timer::tick($interval, $callback);

        return function () use ($tickId) {
            Timer::clear($tickId); // @phpstan-ignore-line
        };
    }
}
