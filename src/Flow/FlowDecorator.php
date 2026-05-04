<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\FlowInterface;
use Flow\Ip;
use Flow\JobInterface;

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
    public function __construct(private FlowInterface $flow) {}

    public function __invoke(Ip $ip): void
    {
        ($this->flow)($ip);
    }

    public function fn(array|Closure|FlowInterface|JobInterface $flow): FlowInterface
    {
        return $this->flow->fn($flow);
    }

    public function await(): void
    {
        $this->flow->await();
    }
}
