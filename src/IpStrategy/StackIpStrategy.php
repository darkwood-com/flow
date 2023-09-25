<?php

declare(strict_types=1);

namespace Flow\IpStrategy;

use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Ip;
use Flow\IpStrategyEvent;
use Flow\IpStrategyInterface;

/**
 * @template T
 *
 * @implements IpStrategyInterface<T>
 */
class StackIpStrategy implements IpStrategyInterface
{
    /**
     * @var array<Ip<T>>
     */
    private array $ips = [];

    public static function getSubscribedEvents()
    {
        return [
            IpStrategyEvent::PUSH => 'push',
            IpStrategyEvent::PULL => 'pull',
        ];
    }

    /**
     * @param PushEvent<T> $event
     */
    public function push(PushEvent $event): void
    {
        $this->ips[] = $event->getIp();
    }

    /**
     * @param PullEvent<T> $event
     */
    public function pull(PullEvent $event): void
    {
        $event->setIp(array_pop($this->ips));
    }
}
