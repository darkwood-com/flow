<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use co;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Flow\Event\PopEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Exception\RuntimeException;
use Flow\Ip;
use OpenSwoole\Timer;
use RuntimeException as NativeRuntimeException;
use Throwable;

use function array_key_exists;
use function extension_loaded;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class SwooleDriver implements DriverInterface
{
    private int $ticks = 0;

    public function __construct()
    {
        if (!extension_loaded('openswoole')) {
            throw new NativeRuntimeException('Swoole extension is not loaded. Suggest install it with pecl install openswoole');
        }
    }

    public function async(Closure $callback): Closure
    {
        return static function (...$args) use ($callback) {
            return static function ($onResolve) use ($callback, $args) {
                go(static function () use ($args, $callback, $onResolve) {
                    try {
                        $return = $callback(...$args, ...($args = []));
                        $onResolve($return);
                    } catch (Throwable $exception) {
                        $onResolve(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
                    }
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
        $async = function (Closure $job) {
            return function (mixed $data) use ($job) {
                $async = $this->async($job);

                return $async($data);
            };
        };

        $defer = static function (Closure $job) {
            return static function (Closure $onResolve) use ($job) {
                go(static function () use ($job, $onResolve) {
                    try {
                        $job($onResolve, static function ($fn, $next) {
                            $fn($next);
                        });
                    } catch (Throwable $exception) {
                        $onResolve(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
                    }
                });
            };
        };

        co::run(function () use (&$stream, $async, $defer) {
            while ($stream['ips'] > 0 or $this->ticks > 0) {
                $nextIp = null;
                do {
                    foreach ($stream['dispatchers'] as $index => $dispatcher) {
                        $nextIp = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIp();
                        if ($nextIp !== null) {
                            $stream['dispatchers'][$index]->dispatch(new AsyncEvent($async, $defer, $stream['fnFlows'][$index]['job'], $nextIp, static function ($data) use (&$stream, $index, $nextIp) {
                                if ($data instanceof RuntimeException and array_key_exists($index, $stream['fnFlows']) && $stream['fnFlows'][$index]['errorJob'] !== null) {
                                    $stream['fnFlows'][$index]['errorJob']($data);
                                } elseif (array_key_exists($index + 1, $stream['fnFlows'])) {
                                    $ip = new Ip($data);
                                    $stream['ips']++;
                                    $stream['dispatchers'][$index + 1]->dispatch(new PushEvent($ip), Event::PUSH);
                                }

                                $stream['dispatchers'][$index]->dispatch(new PopEvent($nextIp), Event::POP);
                                $stream['ips']--;
                            }), Event::ASYNC);
                        }
                    }
                    co::sleep(1);
                } while ($nextIp !== null);
            }
        });
    }

    public function delay(float $seconds): void
    {
        co::sleep((int) $seconds);
    }

    public function tick($interval, Closure $callback): Closure
    {
        $this->ticks++;
        $tickId = Timer::tick((int) $interval, $callback);

        return function () use ($tickId) {
            Timer::clear($tickId); // @phpstan-ignore-line
            $this->ticks--;
        };
    }
}
