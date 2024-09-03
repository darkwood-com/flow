<?php

declare(strict_types=1);

namespace Flow;

use Flow\Event\PoolEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @template T
 */
interface IpStrategyInterface extends EventSubscriberInterface
{
    /**
     * @param PoolEvent<T> $event
     */
    public function pool(PoolEvent $event): void;
}
