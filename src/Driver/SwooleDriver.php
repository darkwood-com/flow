<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Exception\RuntimeException;
use OpenSwoole\Coroutine;
use OpenSwoole\Timer;
use RuntimeException as NativeRuntimeException;
use Throwable;

use function extension_loaded;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class SwooleDriver implements DriverInterface
{
    /**
     * @var array<int, callable(): void>
     */
    private array $ticksIds = [];

    public function __construct()
    {
        if (!extension_loaded('openswoole')) {
            throw new NativeRuntimeException('Swoole extension is not loaded. Suggest install it with pecl install openswoole');
        }
    }

    public function async(Closure $callback, Closure $onResolve = null): Closure
    {
        return static function (...$args) use ($callback, $onResolve): void {
            Coroutine::run(static function () use ($callback, $onResolve, $args) {
                Coroutine::create(static function (Closure $callback, array $args, Closure $onResolve = null) {
                    try {
                        $return = $callback(...$args, ...($args = []));
                        if ($onResolve) {
                            $onResolve($return);
                        }
                    } catch (Throwable $exception) {
                        if ($onResolve) {
                            $onResolve(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
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
            unset($this->ticksIds[$tickId]);
            Timer::clear($tickId); // @phpstan-ignore-line
        };
    }

    public function start(): void
    {
    }

    public function stop(): void
    {
        foreach ($this->ticksIds as $cancel) {
            $cancel();
        }
    }
}
