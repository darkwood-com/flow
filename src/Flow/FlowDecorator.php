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

    public function __invoke(Ip $ip, mixed $context = null): void
    {
        ($this->flow)($ip, $context);
    }

    public function pipe(Closure $callback): void
    {
        $this->flow->pipe($callback);
    }
}
