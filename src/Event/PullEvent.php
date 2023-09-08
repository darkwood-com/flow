<?php

declare(strict_types=1);

namespace Flow\Event;

use Flow\Ip;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @template T
 */
final class PullEvent extends Event
{
    /**
     * @param null|Ip<T> $ip
     */
    private ?Ip $ip = null;

    /**
     * @return null|Ip<T>
     */
    public function getIp(): ?Ip
    {
        return $this->ip;
    }

    /**
     * @return null|Ip<T>
     */
    public function setIp(?Ip $ip)
    {
        $this->ip = $ip;
    }
}
