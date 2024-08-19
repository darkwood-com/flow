<?php

declare(strict_types=1);

namespace Flow\AsyncHandler;

use Flow\AsyncHandlerInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;

/**
 * @template T
 *
 * @implements AsyncHandlerInterface<T>
 */
final class DeferAsyncHandler implements AsyncHandlerInterface
{
    public static function getSubscribedEvents()
    {
        return [
            Event::ASYNC => 'async',
        ];
    }

    public function async(AsyncEvent $event): void
    {
        $ip = $event->getIp();
        $job = $event->getJob();
        $next = $job([$ip->data, $event->getDefer()]);
        $next(static function ($result) use ($event) {
            [$data] = $result;
            $callback = $event->getCallback();
            $callback($data);
        });
    }
}
