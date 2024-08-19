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
final class AsyncHandler implements AsyncHandlerInterface
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
        $async = $event->getAsync();
        $asyncJob = $async($event->getJob());
        $next = $asyncJob($ip->data);
        $next($event->getCallback());
    }
}
