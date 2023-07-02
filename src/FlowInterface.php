<?php

declare(strict_types=1);

namespace Flow;

use Closure;

interface FlowInterface
{
    public function __invoke(Ip $ip, Closure $callback = null): void;

    public function fn(FlowInterface $flow): FlowInterface;
}
