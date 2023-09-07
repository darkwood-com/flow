<?php

declare(strict_types=1);

namespace Flow\IpStrategy;

use Flow\Ip;
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

    /**
     * @param Ip<T> $ip
     */
    public function push(Ip $ip): void
    {
        $this->ips[] = $ip;
    }

    /**
     * @return null|Ip<T> $ip
     */
    public function pop(): ?Ip
    {
        return array_pop($this->ips);
    }

    /**
     * @param Ip<T> $ip
     */
    public function done(Ip $ip): void
    {
    }
}
