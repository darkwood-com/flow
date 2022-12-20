<?php

declare(strict_types=1);

namespace Flow\IpStrategy;

use Flow\Ip;
use Flow\IpStrategyInterface;

class StackIpStrategy implements IpStrategyInterface
{
    /**
     * @var array<Ip>
     */
    private array $ips = [];

    public function push(Ip $ip): void
    {
        $this->ips[] = $ip;
    }

    public function pop(): ?Ip
    {
        return array_pop($this->ips);
    }

    public function done(Ip $ip): void
    {
    }
}
