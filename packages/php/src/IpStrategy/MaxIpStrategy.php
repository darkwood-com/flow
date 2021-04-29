<?php

declare(strict_types=1);

namespace RFBP\IpStrategy;

use RFBP\Ip;
use RFBP\IpStrategyInterface;

class MaxIpStrategy implements IpStrategyInterface
{
    private int $processing = 0;

    public function __construct(private int $max = 1, private ?IpStrategyInterface $ipStrategy = null)
    {
        $this->ipStrategy = $ipStrategy ?? new LinearIpStrategy();
    }

    public function push(Ip $ip): void
    {
        $this->ipStrategy->push($ip);
    }

    public function pop(): ?Ip
    {
        if ($this->processing < $this->max) {
            $ip = $this->ipStrategy->pop();
            if ($ip) {
                ++$this->processing;
            }

            return $ip;
        }

        return null;
    }

    public function done(Ip $ip): void
    {
        $this->ipStrategy->done($ip);
        --$this->processing;
    }
}
