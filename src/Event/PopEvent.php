<?php

declare(strict_types=1);

namespace Flow\Event;

use Flow\Ip;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @template T
 */
final class PopEvent extends Event
{
    /**
     * @param Ip<T> $ip
     */
    private Ip $ip;

    /**
     * @param Ip<T> $ip
     */
    public function __construct(Ip $ip)
    {
        $this->ip = $ip;
    }

    /**
     * @return Ip<T>
     */
    public function getIp(): Ip
    {
        return $this->ip;
    }
}
