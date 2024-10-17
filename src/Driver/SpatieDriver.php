<?php

declare(strict_types=1);

declare(ticks=1000);

namespace Flow\Driver;

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
use Spatie\Async\Pool;
use Throwable;

use function array_key_exists;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class SpatieDriver implements DriverInterface
{
    use DriverTrait;

    private int $ticks = 0;

    private Pool $pool;

    public function __construct()
    {
        if (!class_exists('Spatie\Async\Pool')) {
            throw new NativeRuntimeException('Spatie Async is not loaded. Suggest install it with composer require spatie/async');
        }

        $this->pool = Pool::create();
        if (!$this->pool->isSupported()) {
            throw new NativeRuntimeException('Spatie Async will not run asynchronously. PHP pcntl and posix extension are required');
        }
    }

    public function async(Closure|JobInterface $callback): Closure
    {
        return function (...$args) use ($callback) {
            return function ($onResolve) use ($callback, $args) {
                $this->pool->add(static function () use ($callback, $args) {
                    return $callback(...$args, ...($args = []));
                })->then(static function ($return) use ($onResolve) {
                    $onResolve($return);
                })->catch(static function (Throwable $exception) use ($onResolve) {
                    $onResolve(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
                });
            };
        };
    }

    public function defer(Closure $callback): mixed
    {
        return null;
    }

    public function await(array &$stream): void
    {
        $async = function (Closure|JobInterface $job) {
            return function (mixed $data) use ($job) {
                $async = $this->async($job);

                return $async($data);
            };
        };

        $defer = function (Closure|JobInterface $job) {
            return function (Closure $onResolve) use ($job) {
                $this->pool->add(static function () use ($job, $onResolve) {
                    return $job($onResolve, static function ($fn, $next) {
                        $fn($next);
                    });
                })->catch(static function (Throwable $exception) use ($onResolve) {
                    $onResolve(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
                });
            };
        };

        do {
            foreach ($stream['dispatchers'] as $index => $dispatcher) {
                $nextIps = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIps();
                foreach ($nextIps as $nextIp) {
                    $stream['dispatchers'][$index]->dispatch(new AsyncEvent($async, $defer, $stream['fnFlows'][$index]['job'], $nextIp, static function ($data) use (&$stream, $index, $nextIp) {
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
        } while ($this->countIps($stream['dispatchers']) > 0 or $this->ticks > 0);
    }

    public function delay(float $seconds): void
    {
        sleep((int) $seconds);
    }

    public function tick($interval, Closure $callback): Closure
    {
        $this->ticks++;
        $closure = static fn () => $callback();
        register_tick_function($closure);

        return function () use ($closure) {
            unregister_tick_function($closure);
            $this->ticks--;
        };
    }
}
