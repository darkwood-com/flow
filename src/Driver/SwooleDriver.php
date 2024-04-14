<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use co;
use Flow\DriverInterface;
use Flow\Event\PopEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Exception\RuntimeException;
use Flow\Ip;
use Flow\IpStrategyEvent;
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
        return static function ($onResolve) use ($callback) {
            return static function (...$args) use ($onResolve, $callback) {
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

    public function await(array &$stream): void
    {
        $async = function ($ip, $fnFlows, $index, $onResolve) {
            $async = $this->async($fnFlows[$index]['job']);

            if ($ip->data === null) {
                return $async($onResolve)();
            }

            return $async($onResolve)($ip->data);
        };

        co::run(function () use (&$stream, $async) {
            while ($stream['ips'] > 0 or $this->ticks > 0) {
                $nextIp = null;
                do {
                    foreach ($stream['dispatchers'] as $index => $dispatcher) {
                        $nextIp = $dispatcher->dispatch(new PullEvent(), IpStrategyEvent::PULL)->getIp();
                        if ($nextIp !== null) {
                            $async($nextIp, $stream['fnFlows'], $index, static function ($data) use (&$stream, $index, $nextIp) {
                                if ($data instanceof RuntimeException and array_key_exists($index, $stream['fnFlows']) && $stream['fnFlows'][$index]['errorJob'] !== null) {
                                    $stream['fnFlows'][$index]['errorJob']($data);
                                } elseif (array_key_exists($index + 1, $stream['fnFlows'])) {
                                    $ip = new Ip($data);
                                    $stream['ips']++;
                                    $stream['dispatchers'][$index + 1]->dispatch(new PushEvent($ip), IpStrategyEvent::PUSH);
                                }

                                $stream['dispatchers'][$index]->dispatch(new PopEvent($nextIp), IpStrategyEvent::POP);
                                $stream['ips']--;
                            });
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
