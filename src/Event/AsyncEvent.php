<?php

declare(strict_types=1);

namespace Flow\Event;

use Closure;
use Flow\Ip;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @template T
 */
final class AsyncEvent extends Event
{
    /**
     * @param Ip<T> $ip
     */
    public function __construct(
        private Closure $async,
        private Closure $defer,
        private Closure $job,
        private Ip $ip,
        private Closure $callback
    ) {}

    public function getAsync(): Closure
    {
        return $this->async;
    }

    public function getDefer(): Closure
    {
        return $this->defer;
    }

    public function getJob(): Closure
    {
        return $this->job;
    }

    /**
     * @return Ip<T>
     */
    public function getIp(): Ip
    {
        return $this->ip;
    }

    public function getCallback(): Closure
    {
        return $this->callback;
    }
}
