<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\FlowInterface;
use Flow\Ip;

/**
 * @template T1
 * @template T2
 *
 * @implements FlowInterface<T1>
 */
abstract class FlowDecorator implements FlowInterface
{
    /**
     * @param FlowInterface<T1> $flow
     */
    public function __construct(private FlowInterface $flow)
    {
    }

    /**
     * @param Ip<T1> $ip
     */
    public function __invoke(Ip $ip, Closure $callback = null): void
    {
        ($this->flow)($ip, $callback);
    }

    /**
     * @param FlowInterface<T2> $flow
     *
     * @return FlowInterface<T1>
     */
    public function fn(FlowInterface $flow): FlowInterface
    {
        return $this->flow->fn($flow);
    }
}
