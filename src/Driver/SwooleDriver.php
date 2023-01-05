<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Timer;
use Throwable;

class SwooleDriver implements DriverInterface
{
    /**
     * @var array<mixed>
     */
    private $ticks;

    /**
     * @var array<int>
     */
    private array $ticksIds;

    public function __construct()
    {
        if (!extension_loaded('swoole')) {
            throw new RuntimeException('Swoole extension is not loaded. Suggest install it with pecl install swoole');
        }

        $this->ticks = [];
        $this->ticksIds = [];
    }

    public function async(Closure $callback, ?Closure $onResolved = null): Closure
    {
        return static function (...$args) use ($callback, $onResolved): void {
            Coroutine::create(function (Closure $callback, ?Closure $onResolved, ...$args) {
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
            }, $callback, $onResolved, ...$args);
        };
    }

    public function delay(float $seconds): void
    {
        Coroutine::sleep($seconds);
    }

    public function tick(int $interval, Closure $callback): void
    {
        $this->ticks[] = [$interval, $callback];
    }

    public function start(): void
    {
        foreach ($this->ticks as $tick) {
            [$interval, $callback] = $tick;
            $this->ticksIds[] = Timer::tick($interval, $callback);
        }
    }

    public function stop(): void
    {
        foreach ($this->ticksIds as $tickId) {
            Timer::clear($tickId);
        }
        $this->ticks = [];
        $this->ticksIds = [];
    }
}