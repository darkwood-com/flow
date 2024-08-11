<?php

declare(strict_types=1);

namespace Flow\AsyncHandler;

use Closure;
use Flow\AsyncHandlerInterface;
use Flow\Event;
use Flow\Event\AsyncEvent;

use function call_user_func_array;

final class YAsyncHandler implements AsyncHandlerInterface
{
    public static function getSubscribedEvents()
    {
        return [
            Event::ASYNC => 'async',
        ];
    }

    public function async(AsyncEvent $event): void
    {
        $wrapper = static function ($job) use ($event) {
            $U = static fn (Closure $f) => $f($f);
            $Y = static fn (Closure $f) => $U(static fn (Closure $x) => $f(static fn ($y) => $U($x)($y)));

            return $Y($event->getWrapper()($job));
        };

        $args = array_merge([$wrapper], $event->getArgs());

        call_user_func_array($event->getAsync(), $args);

        /*$U = static fn (Closure $f) => $f($f);
        $Y = static fn (Closure $f) => $U(static fn (Closure $x) => $f(static fn ($y) => $U($x)($y)));

        call_user_func_array($event->getAsync(), $event->getArgs())($Y($event->getWrapper()));*/
    }
}
