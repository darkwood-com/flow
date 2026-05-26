<?php

declare(strict_types=1);

namespace Flow\Event;

use Flow\Ip;
use Flow\IpPool;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @template T
 */
final class PullEvent extends Event
{
    /**
     * @var IpPool<T>
     */
    private IpPool $ipPool;

    public function __construct()
    {
        $this->ipPool = new IpPool();
    }

    /**
     * @return Ip<T>[]
     */
    public function getIps(): array
    {
        return $this->ipPool->getIps();
    }

    /**
     * @param Ip<T> $ip
     */
    public function addIp(Ip $ip): void
    {
        $this->ipPool->addIp($ip);
    }
}
