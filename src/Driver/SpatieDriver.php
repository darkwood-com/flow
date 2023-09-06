<?php

declare(strict_types=1);

declare(ticks=1000);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Exception\RuntimeException;
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
    /**
     * @var array<string>
     */
    private array $ticksIds = [];

    private Pool $pool; /** @phpstan-ignore-line */
    public function __construct()
    {
        if (!class_exists('Spatie\\Async\\Pool')) {
            throw new NativeRuntimeException('Spatie Async is not loaded. Suggest install it with composer require spatie/async');
        }

        $this->pool = Pool::create();
        if (!$this->pool->isSupported()) {
            throw new NativeRuntimeException('Spatie Async will not run asynchronously. PHP PCNTL extension is required');
        }
    }

    public function __serialize()
    {
        return [];
    }

    /**
     * @param array<mixed> $data
     */
    public function __unserialize(array $data)
    {
        $this->pool = Pool::create(); // @phpstan-ignore-line
    }

    public function async(Closure $callback, Closure $onResolve = null): Closure
    {
        return function (...$args) use ($callback, $onResolve): void {
            $this->pool->add(static function () use ($callback, $args) {// @phpstan-ignore-line
                return $callback(...$args, ...($args = []));
            })->then(static function ($return) use ($onResolve) {
                if ($onResolve) {
                    $onResolve($return);
                }
            })->catch(static function (Throwable $exception) use ($onResolve) {
                if ($onResolve) {
                    $onResolve(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
                }
            });
        };
    }

    public function delay(float $seconds): void
    {
        sleep((int) $seconds);
    }

    public function tick(int $interval, Closure $callback): Closure
    {
        $tickId = uniqid('flow_spatie_tick_id');

        $closure = static fn () => $callback();
        register_tick_function($closure);

        $cancel = function () use ($tickId, $closure) {
            unset($this->ticksIds[$tickId]);
            unregister_tick_function($closure);
        };

        $this->ticksIds[$tickId] = $cancel;

        return $cancel;
    }

    public function start(): void
    {
        $this->pool->wait(); // @phpstan-ignore-line
    }

    public function stop(): void
    {
        foreach ($this->ticksIds as $cancel) {
            $cancel();
        }

        $this->pool->stop(); // @phpstan-ignore-line
    }
}
