<?php

declare(strict_types=1);

declare(ticks=1000);

namespace Flow\Driver;

use Closure;
use Fiber;
use Flow\DriverInterface;
use Flow\Exception;
use Throwable;

class FiberDriver implements DriverInterface
{
    /**
     * @var array<mixed>
     */
    private array $fibers = [];

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
        $closure = static fn () => $callback();

        register_tick_function($closure);

        return static fn () => unregister_tick_function($closure);
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
                                $fiber['onResolve'](new Exception($fiber['exception']->getMessage(), $fiber['exception']->getCode(), $fiber['exception']));
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
