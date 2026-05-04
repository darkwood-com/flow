<?php

declare(strict_types=1);

namespace Flow;

/**
 * @template T
 */
class IpPool
{
    /**
     * @var array<Ip<T>>
     */
    private array $ips = [];

    /**
     * @param Ip<T> $ip
     *
     * @return callable A function that removes the added IP from the pool when called
     */
    public function addIp(Ip $ip): callable
    {
        $this->ips[] = $ip;

        return function () use ($ip) {
            $this->ips = array_filter($this->ips, static function ($iteratorIp) use ($ip) {
                return $iteratorIp !== $ip;
            });
        };
    }

    /**
     * @return array<Ip<T>>
     */
    public function getIps(): array
    {
        return $this->ips;
    }

    /**
     * @return null|Ip<T>
     */
    public function shiftIp(): ?Ip
    {
        return array_shift($this->ips);
    }

    /**
     * @return null|Ip<T>
     */
    public function popIp(): ?Ip
    {
        return array_pop($this->ips);
    }
}
