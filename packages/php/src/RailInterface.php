<?php

declare(strict_types=1);

namespace RFBP;

use Closure;

interface RailInterface
{
    public function __invoke(Ip $ip, mixed $context = null): void;

    public function pipe(Closure $callback): void;
}
