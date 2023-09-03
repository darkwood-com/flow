<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
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

    public function async(Closure $callback, Closure $onResolved = null): Closure
    {
        return function (...$args) use ($callback, $onResolved): void {
            Coroutine::run(static function () use ($callback, $onResolved, $args) {
                Coroutine::create(static function (Closure $callback, array $args, Closure $onResolved = null) {
                    try {
                        $callback(...$args, ...($args = []));
                        if ($onResolved) {
                            $onResolved(null);
                        }
                    } catch (Throwable $e) {
                        if ($onResolved) {
                            $onResolved($e);
                        }
                    }
                }, $callback, $args, $onResolved);
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
