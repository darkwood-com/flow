<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Revolt\EventLoop;
use RuntimeException;
use Throwable;

class AmpDriver implements DriverInterface
{
    /**
     * @var array<string>
     */
    private array $ticksIds;

    public function __construct()
    {
        if (!function_exists('Amp\\async')) {
            throw new RuntimeException('Amp is not loaded. Suggest install it with composer require amphp/amp');
        }

        $this->ticksIds = [];
    }

    public function coroutine(Closure $callback, ?Closure $onResolved = null): Closure
    {
        return static function (...$args) use ($callback, $onResolved): void {
            EventLoop::queue(static function (Closure $callback, array $args, ?Closure $onResolved = null) {
                try {
                    $callback(...$args, ...($args = []));
                    if ($onResolved) {
                        $onResolved(null);
                    }
                } catch (Throwable $exception) {
                    if ($onResolved) {
                        $onResolved($exception);
                    }
                }
            }, $callback, $args, $onResolved);
        };
    }

    public function tick(int $interval, Closure $callback): void
    {
        $this->ticksIds[] = EventLoop::repeat($interval / 1000, $callback);
    }

    public function start(): void
    {
        if (!EventLoop::getDriver()->isRunning()) {
            EventLoop::run();
        }
    }

    public function stop(): void
    {
        foreach ($this->ticksIds as $tickId) {
            EventLoop::cancel($tickId);
        }
        $this->ticksIds = [];

        EventLoop::getDriver()->stop();
    }
}
