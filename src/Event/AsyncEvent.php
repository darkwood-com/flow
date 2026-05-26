<?php

declare(strict_types=1);

namespace Flow\Event;

use Closure;
use Flow\Ip;
use Flow\JobInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @template T1
 */
final class AsyncEvent extends Event
{
    /**
     * @param Closure|JobInterface<mixed,mixed> $job
     * @param Ip<T1>                            $ip
     */
    public function __construct(
        private Closure $async,
        private Closure $defer,
        private Closure|JobInterface $job,
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

    /**
     * @return Closure|JobInterface<mixed,mixed>
     */
    public function getJob(): Closure|JobInterface
    {
        return $this->job;
    }

    /**
     * @return Ip<T1>
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
