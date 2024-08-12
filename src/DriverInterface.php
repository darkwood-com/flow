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
     * @param Closure(TArgs): TReturn $callback
     */
    public function async(Closure $callback): Closure;

    /**
     * This allow more granular control on async
     * $callback will be given two callbacks
     * - an complete callback to store result
     * - an async callback to go to the next async call.
     */
    public function defer(Closure $callback): mixed;

    /**
     * @param array{'ips': int, 'fnFlows': array<mixed>, 'dispatchers': array<mixed>} $stream
     */
    public function await(array &$stream): void;

    public function delay(float $seconds): void;

    /**
     * @param float           $interval in seconds
     * @param Closure(): void $callback
     *
     * @return Closure(): void when called, this cleanup tick interval
     */
    public function tick(float $interval, Closure $callback): Closure;
}
