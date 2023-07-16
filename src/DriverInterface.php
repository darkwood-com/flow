<?php

declare(strict_types=1);

namespace Flow;

use Closure;

interface DriverInterface
{
    /**
     * @return Closure when called, this start async $callback
     */
    public function async(Closure $callback, Closure $onResolved = null): Closure;

    public function delay(float $seconds): void;

    /**
     * @return Closure when called, this cleanup tick interval
     */
    public function tick(int $interval, Closure $callback): Closure;
}
