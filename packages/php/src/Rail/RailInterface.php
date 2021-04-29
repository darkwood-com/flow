<?php

declare(strict_types=1);

namespace RFBP\Rail;

use Closure;
use Symfony\Component\Messenger\Envelope as Ip;

interface RailInterface
{
    public function __invoke(Ip $ip, mixed $context = null): void;

    public function pipe(Closure $callback): void;
}
