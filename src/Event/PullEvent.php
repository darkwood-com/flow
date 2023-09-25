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
     * @param null|Ip<T> $ip
     */
    public function setIp(?Ip $ip): void
    {
        $this->ip = $ip;
    }
}
