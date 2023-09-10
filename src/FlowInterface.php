<?php

declare(strict_types=1);

namespace Flow;

use Closure;

/**
 * @template T1
 */
interface FlowInterface
{
    /**
     * @param Ip<T1>                     $ip
     * @param null|Closure(Ip<T1>): void $callback
     */
    public function __invoke(Ip $ip, Closure $callback = null): void;

    /**
     * @template T2
     *
     * @param FlowInterface<T2> $flow
     *
     * @return FlowInterface<T1>
     */
    public function fn(self $flow): self;
}
