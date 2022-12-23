<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\Ip;
use Flow\FlowInterface;

abstract class FlowDecorator implements FlowInterface
{
    public function __construct(private FlowInterface $flow)
    {
    }

    public function __invoke(Ip $ip, ?Closure $callback = null): void
    {
        ($this->flow)($ip, $callback);
    }

    public function fn(FlowInterface $flow): FlowInterface
    {
        return $this->flow->fn($flow);
    }
}
