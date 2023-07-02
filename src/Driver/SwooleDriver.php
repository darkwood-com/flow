<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use OpenSwoole\Coroutine;
use OpenSwoole\Timer;
use RuntimeException;
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
        if (!extension_loaded('openswoole')) {
            throw new RuntimeException('Swoole extension is not loaded. Suggest install it with pecl install openswoole');
        }

        $this->ticks = [];
        $this->ticksIds = [];
    }

    public function async(Closure $callback, Closure $onResolved = null): Closure
    {
        return static function (...$args) use ($callback, $onResolved): void {
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
            Timer::clear($tickId); // @phpstan-ignore-line
        }
        $this->ticks = [];
        $this->ticksIds = [];
    }
}
