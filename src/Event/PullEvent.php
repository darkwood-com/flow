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
     * @var null|Ip<T>
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
