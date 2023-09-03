<?php

declare(strict_types=1);

declare(ticks=1000);

namespace Flow\Driver;

use Closure;
use Fiber;
use Flow\DriverInterface;
use Throwable;

class FiberDriver implements DriverInterface
{
    /**
     * @var array<mixed>
     */
    private array $fibers = [];

    private bool $isLooping = false;

    public function async(Closure $callback, Closure $onResolved = null): Closure
    {
        return function (...$args) use ($callback, $onResolved): void {
            $fiber = new Fiber($callback);

            $fiberData = [
                'fiber' => $fiber,
                'onResolved' => $onResolved,
                'error' => null,
            ];

            try {
                $fiber->start(...$args);
            } catch (Throwable $e) {
                $fiberData['error'] = $e;
            }

            $this->fibers[] = $fiberData;
            $this->loop();
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
        $closure = fn () => $callback();

        register_tick_function($closure);

        return fn () => unregister_tick_function($closure);
    }

    private function loop(): void
    {
        if ($this->isLooping === false) {
            $this->isLooping = true;

            $isRunning = true;

            while ($isRunning) {
                $isRunning = false;

                foreach ($this->fibers as $i => $fiber) {
                    $isRunning = $isRunning || !$fiber['fiber']->isTerminated();

                    if (!$fiber['fiber']->isTerminated() && $fiber['fiber']->isSuspended()) {
                        try {
                            $fiber['fiber']->resume();
                        } catch (Throwable $e) {
                            $this->fibers[$i]['error'] = $e;
                        }
                    } else {
                        if ($fiber['onResolved']) {
                            if ($fiber['error'] === null) {
                                $fiber['onResolved'](null);
                            } else {
                                $fiber['onResolved']($fiber['error']);
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

        $this->isLooping = false;
    }
}
