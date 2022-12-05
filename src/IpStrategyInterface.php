<?php

declare(strict_types=1);

namespace Flow;

interface IpStrategyInterface
{
    public function push(Ip $ip): void;

    public function pop(): ?Ip;

    public function done(Ip $ip): void;
}
