<?php

declare(strict_types=1);

declare(ticks=1000);

namespace Flow\Driver;

use Closure;
use Fiber;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Flow\Event\PopEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Exception\RuntimeException;
use Flow\Ip;
use Throwable;

use function array_key_exists;
use function count;

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
    private array $ticks = [];

    public function async(Closure $callback): Closure
    {
        return static function () {};
    }

    public function defer(Closure $callback): mixed
    {
        return null;
    }

    public function await(array &$stream): void
    {
        $async = static function ($ip, $index, $isTick) use (&$fiberDatas) {
            return static function (Closure $job) use (&$fiberDatas, $ip, $index, $isTick) {
                return static function (mixed $data) use (&$fiberDatas, $ip, $index, $isTick, $job) {
                    $fiber = new Fiber($job);

                    $exception = null;

                    try {
                        $fiber->start($data);
                    } catch (Throwable $fiberException) {
                        $exception = $fiberException;
                    }

                    $fiberDatas[] = [
                        'fiber' => $fiber,
                        'exception' => $exception,
                        'ip' => $ip,
                        'index' => $index,
                        'isTick' => $isTick,
                    ];

                    return static function () {};
                };
            };
        };

        $defer = static function ($ip, $index, $isTick) use ($async) {
            return $async($ip, $index, $isTick);
        };

        $tick = 0;
        $fiberDatas = [];
        while ($stream['ips'] > 0 or count($this->ticks) > 0) {
            foreach ($this->ticks as [
                'interval' => $interval,
                'callback' => $callback,
            ]) {
                if ($tick % $interval === 0) {
                    $ip = new Ip();
                    $stream['ips']++;
                    $async($ip, 0, true)($callback)($ip->data);
                }
            }

            $nextIp = null;
            do {
                foreach ($stream['dispatchers'] as $index => $dispatcher) {
                    $nextIp = $dispatcher->dispatch(new PullEvent(), Event::PULL)->getIp();
                    if ($nextIp !== null) {
                        $stream['dispatchers'][$index]->dispatch(new AsyncEvent(static function (Closure $job) use ($async, $nextIp, $index) {
                            return $async($nextIp, $index, false)($job);
                        }, static function (Closure $job) use ($defer, $nextIp, $index) {
                            return $defer($nextIp, $index, false)($job);
                        }, $stream['fnFlows'][$index]['job'], $nextIp, static function () {}), Event::ASYNC);
                    }
                }
            } while ($nextIp !== null);

            foreach ($fiberDatas as $i => $fiberData) { // @phpstan-ignore-line see https://github.com/phpstan/phpstan/issues/11468
                if (!$fiberData['fiber']->isTerminated() and $fiberData['fiber']->isSuspended()) {
                    try {
                        $fiberData['fiber']->resume();
                    } catch (Throwable $exception) {
                        $fiberDatas[$i]['exception'] = $exception;
                    }
                } else {
                    if ($fiberData['exception'] === null) {
                        $data = $fiberData['fiber']->getReturn();

                        if ($fiberData['isTick'] === false and array_key_exists($fiberData['index'] + 1, $stream['fnFlows'])) {
                            $ip = new Ip($data);
                            $stream['ips']++;
                            $stream['dispatchers'][$fiberData['index'] + 1]->dispatch(new PushEvent($ip), Event::PUSH);
                        }
                    } elseif (array_key_exists($fiberData['index'], $stream['fnFlows']) and $stream['fnFlows'][$fiberData['index']]['errorJob'] !== null) {
                        $stream['fnFlows'][$fiberData['index']]['errorJob'](
                            new RuntimeException($fiberData['exception']->getMessage(), $fiberData['exception']->getCode(), $fiberData['exception'])
                        );
                    }
                    $stream['dispatchers'][$fiberData['index']]->dispatch(new PopEvent($fiberData['ip']), Event::POP);
                    $stream['ips']--;
                    unset($fiberDatas[$i]);
                }
            }

            $tick++;
        }
    }

    public function delay(float $seconds): void
    {
        $date = time();
        do {
            Fiber::suspend();
        } while (time() - $date < $seconds);
    }

    public function tick($interval, Closure $callback): Closure
    {
        $i = count($this->ticks) - 1;
        $this->ticks[$i] = [
            'interval' => $interval,
            'callback' => $callback,
        ];

        return function () use ($i) {
            unset($this->ticks[$i]);
        };
    }
}
