<?php

declare(strict_types=1);

namespace Flow;

use Flow\Event\AsyncEvent;
use Flow\Event\PoolEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @template T
 */
interface AsyncHandlerInterface extends EventSubscriberInterface
{
    /**
     * @param AsyncEvent<T> $event
     */
    public function async(AsyncEvent $event): void;

    /**
     * @param PoolEvent<T> $event
     */
    public function pool(PoolEvent $event): void;
}
