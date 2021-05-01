<?php

declare(strict_types=1);

namespace RFBP\Rail;

use Closure;
use RFBP\Ip;
use RFBP\RailInterface;

abstract class RailDecorator implements RailInterface
{
    public function __construct(private RailInterface $rail)
    {
    }

    public function __invoke(Ip $ip, mixed $context = null): void
    {
        ($this->rail)($ip, $context);
    }

    public function pipe(Closure $callback): void
    {
        $this->rail->pipe($callback);
    }
}
