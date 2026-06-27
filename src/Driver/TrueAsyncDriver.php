<?php

declare(strict_types=1);

namespace Flow\Driver;

use Async\Future;
use Async\FutureState;
use Closure;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Flow\Event\PopEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Exception\RuntimeException;
use Flow\Ip;
use Flow\JobInterface;
use RuntimeException as NativeRuntimeException;
use Throwable;

use function array_key_exists;
use function extension_loaded;
use function function_exists;
use function round;

/**
 * Experimental driver backed by the TrueAsync ext-async extension.
 *
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
final class TrueAsyncDriver implements DriverInterface
{
    use DriverTrait;

    private int $ticks = 0;

    private bool $tickCancelled = false;

    public function __construct()
    {
        if (!self::isSupported()) {
            throw new NativeRuntimeException(
                'TrueAsync extension is not loaded. Build PHP with ext-async or use FiberDriver.'
            );
        }
    }

    public static function isSupported(): bool
    {
        return extension_loaded('async') && function_exists('Async\spawn');
    }

    public function async(Closure|JobInterface $callback): Closure
    {
        return static function (...$args) use ($callback) {
            return \Async\spawn(static function () use ($callback, $args) {
                try {
                    return $callback(...$args);
                } catch (Throwable $exception) {
                    return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
                }
            });
        };
    }

    public function defer(Closure $callback): mixed
    {
        $state = new FutureState();
        $future = new Future($state);

        \Async\spawn(static function () use ($callback, $state): void {
            try {
                $callback(
                    static function ($return) use ($state): void {
                        $state->complete($return);
                    },
                    static function ($fn, $next): void {
                        $fn($next);
                    }
                );
            } catch (Throwable $exception) {
                $state->complete(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
            }
        });

        return $future;
    }

    public function await(array &$stream): void
    {
        $async = function (Closure|JobInterface $job) {
            return function (mixed $data) use ($job) {
                $coroutine = $this->async($job)($data);

                return static function (Closure $map) use ($coroutine): void {
                    \Async\spawn(static function () use ($coroutine, $map): void {
                        $result = \Async\await($coroutine);
                        $map($result);
                    });
                };
            };
        };

        $defer = function (Closure|JobInterface $job) {
            return function (Closure $map) use ($job): void {
                $future = $this->defer($job);
                $future->map(static function (mixed $value) use ($map): null {
                    $map($value);

                    return null;
                })->ignore();
            };
        };

        $loop = function () use (&$loop, &$stream, $async, $defer): void {
            foreach ($stream['dispatchers'] as $index => $dispatcher) {
                $nextIps = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIps();
                foreach ($nextIps as $nextIp) {
                    $job = $stream['fnFlows'][$index]['job'];

                    $stream['dispatchers'][$index]->dispatch(new AsyncEvent($async, $defer, $job, $nextIp, static function ($data) use (&$stream, $index, $nextIp): void {
                        if ($data instanceof RuntimeException && array_key_exists($index, $stream['fnFlows']) && $stream['fnFlows'][$index]['errorJob'] !== null) {
                            $stream['fnFlows'][$index]['errorJob']($data);
                        } elseif (array_key_exists($index + 1, $stream['fnFlows'])) {
                            $ip = new Ip($data);
                            $stream['dispatchers'][$index + 1]->dispatch(new PushEvent($ip), Event::PUSH);
                        }

                        $stream['dispatchers'][$index]->dispatch(new PopEvent($nextIp), Event::POP);
                    }), Event::ASYNC);
                }
            }

            if ($this->countIps($stream['dispatchers']) > 0 || $this->ticks > 0) {
                $next = \Async\spawn($loop);
                \Async\await($next);
            }
        };

        $root = \Async\spawn($loop);
        \Async\await($root);
    }

    public function delay(float $seconds): void
    {
        \Async\delay((int) round($seconds * 1000));
    }

    public function tick(float $interval, Closure $callback): Closure
    {
        $this->ticks++;
        $this->tickCancelled = false;
        $intervalMs = (int) round($interval * 1000);

        $schedule = null;
        $schedule = function () use (&$schedule, $intervalMs, $callback): void {
            if ($this->tickCancelled) {
                return;
            }

            \Async\delay($intervalMs);
            $callback();
            \Async\spawn($schedule);
        };

        \Async\spawn($schedule);

        return function (): void {
            $this->tickCancelled = true;
            $this->ticks--;
        };
    }
}
