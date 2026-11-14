<?php

declare(strict_types=1);

namespace Flow\Driver;

use Closure;
use Flow\Event;
use Flow\Event\AsyncEvent;
use Flow\Event\PoolEvent;
use Flow\Ip;
use Flow\JobInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function count;

trait DriverTrait
{
    /**
     * @param array<EventDispatcherInterface> $dispatchers
     */
    public function countIps(array $dispatchers): int
    {
        $count = 0;
        foreach ($dispatchers as $dispatcher) {
            $count += count($dispatcher->dispatch(new PoolEvent(), Event::POOL)->getIps());
        }

        return $count;
    }

    /**
     * @param array<mixed>                      $stream
     * @param Closure|JobInterface<mixed,mixed> $job
     * @param Ip<mixed>                         $ip
     *
     * @return AsyncEvent<mixed>
     */
    private function createAsyncEvent(
        array &$stream,
        Closure $async,
        Closure $defer,
        Closure|JobInterface $job,
        Ip $ip,
        int $index,
    ): AsyncEvent {
        $event = new AsyncEvent(
            $async,
            $defer,
            $job,
            $ip,
            static function (mixed $data) use (&$stream, $index, $ip): void {
                ($stream['complete'])($index, $ip, $data);
            },
        );
        $event->onFailure(static function (Throwable $exception, bool $settled) use (&$stream, $index, $ip): void {
            if ($settled) {
                ($stream['reportFailure'])($exception);

                return;
            }

            ($stream['failIp'])($index, $ip, $exception);
        });

        return $event;
    }

    /**
     * @param AsyncEvent<mixed> $event
     */
    private function dispatchAsyncEvent(EventDispatcherInterface $dispatcher, AsyncEvent $event): void
    {
        try {
            $dispatcher->dispatch($event, Event::ASYNC);
        } catch (Throwable $exception) {
            $event->fail($exception);
        }
    }
}
