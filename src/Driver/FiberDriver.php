<?php

declare(strict_types=1);

declare(ticks=1000);

namespace Flow\Driver;

use Closure;
use Fiber;
use Flow\DriverInterface;
use Flow\Exception\RuntimeException;
use Throwable;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class FiberDriver implements DriverInterface
{
    /**
     * @var array<mixed>
     */
    private array $fibers = [];

    /**
     * @var array<string>
     */
    private array $ticksIds = [];

    private bool $isLooping = false;

    public function async(Closure $callback, Closure $onResolve = null): Closure
    {
        return function (...$args) use ($callback, $onResolve): void {
            $fiber = new Fiber($callback);

            $fiberData = [
                'fiber' => $fiber,
                'onResolve' => $onResolve,
                'exception' => null,
            ];

            try {
                $fiber->start(...$args);
            } catch (Throwable $exception) {
                $fiberData['exception'] = $exception;
            }

            $this->fibers[] = $fiberData;
        };
    }

    public function delay(float $seconds): void
    {
        sleep((int) $seconds);
    }

    /**
     * @param int $interval is hardcoded for now from declare(ticks=1000)
     */
    public function tick(int $interval, Closure $callback): Closure
    {
        $tickId = uniqid('flow_fiber_tick_id');

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
        $this->isLooping = true;

        $isRunning = true;

        while ($this->isLooping || $isRunning) { /** @phpstan-ignore-line */
            $isRunning = false;

            foreach ($this->fibers as $i => $fiber) {
                $isRunning = $isRunning || !$fiber['fiber']->isTerminated();

                if (!$fiber['fiber']->isTerminated() and $fiber['fiber']->isSuspended()) {
                    try {
                        $fiber['fiber']->resume();
                    } catch (Throwable $exception) {
                        $this->fibers[$i]['exception'] = $exception;
                    }
                } else {
                    if ($fiber['onResolve']) {
                        if ($fiber['exception'] === null) {
                            $fiber['onResolve']($fiber['fiber']->getReturn());
                        } else {
                            $fiber['onResolve'](new RuntimeException($fiber['exception']->getMessage(), $fiber['exception']->getCode(), $fiber['exception']));
                        }
                    }
                    unset($this->fibers[$i]);
                }
            }

            if (Fiber::getCurrent()) {
                Fiber::suspend();
                usleep(1_000);
            }
        }
    }

    public function stop(): void
    {
        foreach ($this->ticksIds as $cancel) {
            $cancel();
        }

        $this->isLooping = false;
    }
}
