<?php

declare(strict_types=1);

namespace Flow;

use Closure;

interface DriverInterface
{
    public function coroutine(Closure $callback, ?Closure $onResolved = null): Closure;

    public function tick(int $interval, Closure $callback): void;

    public function run(): void;

    public function stop(): void;
}
