<?php

declare(strict_types=1);

declare(ticks=1000);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Exception\RuntimeException;
use RuntimeException as NativeRuntimeException;
use Symplely\Async\Pool;
use Throwable;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class SymplelyDriver implements DriverInterface
{
    /**
     * @var array<string>
     */
    private array $ticksIds = [];

    public function __construct()
    {
        if (!class_exists('Async\\Coroutine\\Coroutine')) {
            throw new NativeRuntimeException('Symplely Coroutine is not loaded. Suggest install it with composer require symplely/coroutine');
        }
    }

    public function async(Closure $callback, Closure $onResolve = null): Closure
    {
        return function (...$args) use ($callback, $onResolve): void {
            \coroutine_run(static function (Closure $callback, array $args, Closure $onResolve = null) {
                try {
                    $return = yield $callback(...$args, ...($args = []));
                    if ($onResolve) {
                        $onResolve($return);
                    }
                } catch (Throwable $exception) {
                    if ($onResolve) {
                        $onResolve(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
                    }
                }
            }, $callback, $args, $onResolve);
        };
    }

    public function delay(float $seconds): void
    {
        sleep((int) $seconds);
    }

    public function tick(int $interval, Closure $callback): Closure
    {
        return function() {

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
