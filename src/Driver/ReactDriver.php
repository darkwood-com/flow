<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Exception;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Throwable;

use function function_exists;
use function React\Async\async;
use function React\Async\delay;

class ReactDriver implements DriverInterface
{
    private LoopInterface $eventLoop;

    private int $counter = 0;

    public function __construct(LoopInterface $eventLoop = null)
    {
        if (!function_exists('React\\Async\\async')) {
            throw new RuntimeException('ReactPHP is not loaded. Suggest install it with composer require react/event-loop');
        }

        $this->eventLoop = $eventLoop ?? Loop::get();
    }

    public function async(Closure $callback, Closure $onResolve = null): Closure
    {
        return function (...$args) use ($callback, $onResolve): void {
            async(function () use ($callback, $onResolve, $args) {
                try {
                    $return = $callback(...$args, ...($args = []));
                    if ($onResolve) {
                        $onResolve($return);
                    }
                } catch (Throwable $exception) {
                    if ($onResolve) {
                        $onResolve(new Exception($exception->getMessage(), $exception->getCode(), $exception));
                    }
                } finally {
                    $this->pop();
                }
            })();
            $this->push();
        };
    }

    public function delay(float $seconds): void
    {
        delay($seconds);
    }

    public function tick(int $interval, Closure $callback): Closure
    {
        $tickId = $this->eventLoop->addPeriodicTimer($interval, $callback);
        $this->push();

        return function () use ($tickId) {
            $this->eventLoop->cancelTimer($tickId);
            $this->pop();
        };
    }

    private function push(): void
    {
        if ($this->counter === 0) {
            $this->eventLoop->run();
        }
        $this->counter++;
    }

    private function pop(): void
    {
        $this->counter--;
        if ($this->counter === 0) {
            $this->eventLoop->stop();
        }
    }
}
