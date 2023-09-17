<?php

declare(strict_types=1);

namespace Flow;

use Closure;

/**
 * @template TArgs TArgs is supposed to be list of generic templating arguments https://github.com/phpstan/phpstan/issues/6873
 * @template TReturn
 */
interface DriverInterface
{
    /**
     * #return Closure(TArgs): void when called this start async $callback.
     *
     * @param Closure(TArgs): TReturn                        $callback
     * @param null|Closure(ExceptionInterface|TReturn): void $onResolve
     */
    public function async(Closure $callback, Closure $onResolve = null): Closure;

    public function delay(float $seconds): void;

    /**
     * @param Closure(): void $callback
     *
     * @return Closure(): void when called, this cleanup tick interval
     */
    public function tick(int $interval, Closure $callback): Closure;

    public function start(): void;

    public function stop(): void;
}
