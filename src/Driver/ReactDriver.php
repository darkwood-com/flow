<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\DriverInterface;
use Flow\Event\PopEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Exception\RuntimeException;
use Flow\Ip;
use Flow\IpStrategyEvent;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use RuntimeException as NativeRuntimeException;
use Throwable;

use function array_key_exists;
use function function_exists;
use function React\Async\async;
use function React\Async\delay;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements DriverInterface<TArgs,TReturn>
 */
class ReactDriver implements DriverInterface
{
    private int $ticks = 0;

    private LoopInterface $eventLoop;

    public function __construct(?LoopInterface $eventLoop = null)
    {
        if (!function_exists('React\\Async\\async')) {
            throw new NativeRuntimeException('ReactPHP is not loaded. Suggest install it with composer require react/event-loop');
        }

        $this->eventLoop = $eventLoop ?? Loop::get();
    }

    public function async(Closure $callback): Closure
    {
        return static function (...$args) use ($callback) {
            return async(static function () use ($callback, $args) {
                try {
                    return $callback(...$args, ...($args = []));
                } catch (Throwable $exception) {
                    return new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
                }
            })();
        };
    }

    public function await(array &$stream): void
    {
        $async = function ($ip, $fnFlows, $index) {
            $async = $this->async($fnFlows[$index]['job']);

            if ($ip->data === null) {
                return $async();
            }

            return $async($ip->data);
        };

        $loop = function () use (&$loop, &$stream, $async) {
            $nextIp = null;
            do {
                foreach ($stream['dispatchers'] as $index => $dispatcher) {
                    $nextIp = $dispatcher->dispatch(new PullEvent(), IpStrategyEvent::PULL)->getIp();
                    if ($nextIp !== null) {
                        $async($nextIp, $stream['fnFlows'], $index)
                            ->then(static function ($data) use (&$stream, $index, $nextIp) {
                                if ($data instanceof RuntimeException and array_key_exists($index, $stream['fnFlows']) && $stream['fnFlows'][$index]['errorJob'] !== null) {
                                    $stream['fnFlows'][$index]['errorJob']($data);
                                } elseif (array_key_exists($index + 1, $stream['fnFlows'])) {
                                    $ip = new Ip($data);
                                    $stream['ips']++;
                                    $stream['dispatchers'][$index + 1]->dispatch(new PushEvent($ip), IpStrategyEvent::PUSH);
                                }

                                $stream['dispatchers'][$index]->dispatch(new PopEvent($nextIp), IpStrategyEvent::POP);
                                $stream['ips']--;
                            })
                        ;
                    }
                }
            } while ($nextIp !== null);

            if ($stream['ips'] > 0 or $this->ticks > 0) {
                $this->eventLoop->futureTick($loop);
            } else {
                $this->eventLoop->stop();
            }
        };
        $this->eventLoop->futureTick($loop);

        $this->eventLoop->run();
    }

    public function delay(float $seconds): void
    {
        delay($seconds);
    }

    public function tick($interval, Closure $callback): Closure
    {
        $this->ticks++;
        $timer = $this->eventLoop->addPeriodicTimer($interval, $callback);

        return function () use ($timer) {
            $this->eventLoop->cancelTimer($timer);
            $this->ticks--;
        };
    }
}
