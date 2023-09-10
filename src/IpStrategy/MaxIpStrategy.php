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
class MaxIpStrategy implements IpStrategyInterface
{
    /**
     * @var IpStrategyInterface<T>
     */
    private IpStrategyInterface $ipStrategy;

    private int $processing = 0;

    /**
     * @param null|IpStrategyInterface<T> $ipStrategy
     */
    public function __construct(private int $max = 1, IpStrategyInterface $ipStrategy = null)
    {
        $this->ipStrategy = $ipStrategy ?? new LinearIpStrategy();
    }

    /**
     * @param Ip<T> $ip
     */
    public function push(Ip $ip): void
    {
        $this->ipStrategy->push($ip);
    }

    /**
     * @return null|Ip<T>
     */
    public function pop(): ?Ip
    {
        if ($this->processing < $this->max) {
            $ip = $this->ipStrategy->pop();
            if ($ip) {
                $this->processing++;
            }

            return $ip;
        }

        return null;
    }

    /**
     * @param Ip<T> $ip
     */
    public function done(Ip $ip): void
    {
        $this->ipStrategy->done($ip);
        $this->processing--;
    }
}
