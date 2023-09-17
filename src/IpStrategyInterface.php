<?php

declare(strict_types=1);

namespace Flow;

/**
 * @template T
 */
interface IpStrategyInterface
{
    /**
     * @param Ip<T> $ip
     */
    public function push(Ip $ip): void;

    /**
     * @return null|Ip<T>
     */
    public function pop(): ?Ip;

    /**
     * @param Ip<T> $ip
     */
    public function done(Ip $ip): void;
}
