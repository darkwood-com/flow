<?php

declare(strict_types=1);

namespace Flow\IpStrategy;

use Flow\Event\PullEvent;
use Flow\Ip;
use Flow\IpStrategyEvent;
use Flow\IpStrategyInterface;

/**
 * @template T
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

    public function push(Ip $ip): void
    {
        $this->ips[] = $ip;
    }

    /**
     * @return PullEvent<T> $event
     */
    public function pull(PullEvent $event): void
    {
        $event->setIp(array_pop($this->ips));
    }
}
