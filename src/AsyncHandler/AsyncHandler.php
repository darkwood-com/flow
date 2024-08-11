<?php

declare(strict_types=1);

namespace Flow\AsyncHandler;

use Flow\AsyncHandlerInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;

use function call_user_func_array;

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
        $args = array_merge([$event->getWrapper()], $event->getArgs());

        call_user_func_array($event->getAsync(), $args);

        // call_user_func_array($event->getAsync(), $event->getArgs())($event->getWrapper());
    }
}
