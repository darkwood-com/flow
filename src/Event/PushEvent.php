<?php

declare(strict_types=1);

namespace Flow\Event;

use Flow\Ip;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @template T
 */
final class PushEvent extends Event
{
    /**
     * @var Ip<T>
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
