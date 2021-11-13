<?php

declare(strict_types=1);

namespace RFBP\Driver;

use Closure;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use RFBP\DriverInterface;
use RuntimeException;
use Throwable;

class ReactDriver implements DriverInterface
{
    private LoopInterface $eventLoop;

    /**
     * @var array<TimerInterface>
     */
    private array $ticksIds;

    public function __construct(?LoopInterface $eventLoop = null)
    {
        if (!interface_exists('React\\EventLoop\\LoopInterface')) {
            throw new RuntimeException('ReactPHP is not loaded. Suggest install it with composer require react/event-loop');
        }

        if (null === $eventLoop) {
            $this->eventLoop = Loop::get();
        } else {
            $this->eventLoop = $eventLoop;
        }

        $this->ticksIds = [];
    }

    public function coroutine(Closure $callback, ?Closure $onResolved = null): Closure
    {
        return function (...$args) use ($callback, $onResolved): void {
            $this->eventLoop->futureTick(static function () use ($callback, $onResolved, $args) {
                try {
                    $callback(...$args);
                    if ($onResolved) {
                        $onResolved(null);
                    }
                } catch (Throwable $e) {
                    if ($onResolved) {
                        $onResolved($e);
                    }
                }
            });
        };
    }

    public function tick(int $interval, Closure $callback): void
    {
        $this->ticksIds[] = $this->eventLoop->addPeriodicTimer($interval, $callback);
    }

    public function run(): void
    {
        $this->eventLoop->run();
    }

    public function stop(): void
    {
        foreach ($this->ticksIds as $tickId) {
            $this->eventLoop->cancelTimer($tickId);
        }
        $this->ticksIds = [];

        $this->eventLoop->stop();
    }
}
