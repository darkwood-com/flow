<?php

declare(strict_types=1);

namespace Flow;

use Closure;

interface DriverInterface
{
    /**
     * @param Closure $onResolve called on resolved and first argument is $callback return or Flow\Exception on Exception
     *
     * @return Closure when called, this start async $callback
     */
    public function async(Closure $callback, Closure $onResolve = null): Closure;

    public function delay(float $seconds): void;

    /**
     * @return Closure when called, this cleanup tick interval
     */
    public function tick(int $interval, Closure $callback): Closure;
}
