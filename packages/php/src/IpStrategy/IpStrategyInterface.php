<?php

declare(strict_types=1);

namespace RFBP\IpStrategy;

use Symfony\Component\Messenger\Envelope as Ip;

interface IpStrategyInterface
{
    public function push(Ip $ip): void;

    public function pop(): ?Ip;

    public function done(Ip $ip): void;
}
