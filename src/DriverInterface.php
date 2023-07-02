<?php

declare(strict_types=1);

namespace Flow;

use Closure;

interface DriverInterface
{
    public function async(Closure $callback, Closure $onResolved = null): Closure;

    public function delay(float $seconds): void;

    public function tick(int $interval, Closure $callback): void;

    public function start(): void;

    public function stop(): void;
}
