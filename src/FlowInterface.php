<?php

declare(strict_types=1);

namespace Flow;

use Closure;

interface FlowInterface
{
    public function __invoke(Ip $ip, mixed $context = null): void;

    public function pipe(Closure $callback): void;
}
