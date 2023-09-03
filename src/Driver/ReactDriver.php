<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use RuntimeException;
use Throwable;

use function function_exists;
use function React\Async\async;
use function React\Async\delay;

class ReactDriver implements DriverInterface
{
    private LoopInterface $eventLoop;

    /**
     * @var array<TimerInterface>
     */
    private array $ticksIds;

    public function __construct(LoopInterface $eventLoop = null)
    {
        if (!function_exists('React\\Async\\async')) {
            throw new RuntimeException('ReactPHP is not loaded. Suggest install it with composer require react/event-loop');
        }

        $this->eventLoop = $eventLoop ?? Loop::get();
        $this->ticksIds = [];
    }

    public function async(Closure $callback, Closure $onResolved = null): Closure
    {
        return static function (...$args) use ($callback, $onResolved): void {
            async(static function () use ($callback, $onResolved, $args) {
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
            })();
        };
    }

    public function delay(float $seconds): void
    {
        delay($seconds);
    }

    public function tick(int $interval, Closure $callback): void
    {
        $this->ticksIds[] = $this->eventLoop->addPeriodicTimer($interval, $callback);
    }

    public function start(): void
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
