<?php

declare(strict_types=1);

namespace RFBP;

interface IpStrategyInterface
{
    public function push(Ip $ip): void;

    public function pop(): ?Ip;

    public function done(Ip $ip): void;
}
