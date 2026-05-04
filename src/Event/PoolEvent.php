<?php

declare(strict_types=1);

namespace Flow\Event;

use Flow\Ip;
use Flow\IpPool;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @template T
 */
final class PoolEvent extends Event
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
     * @param array<Ip<T>> $ips
     */
    public function addIps(array $ips): void
    {
        foreach ($ips as $ip) {
            $this->ipPool->addIp($ip);
        }
    }

    /**
     * @return array<Ip<T>>
     */
    public function getIps(): array
    {
        return $this->ipPool->getIps();
    }
}
