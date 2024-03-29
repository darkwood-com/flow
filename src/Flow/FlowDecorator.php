<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\FlowInterface;
use Flow\Ip;

/**
 * @template T1
 * @template T2
 *
 * @implements FlowInterface<T1>
 */
abstract class FlowDecorator implements FlowInterface
{
    /**
     * @param FlowInterface<T1> $flow
     */
    public function __construct(private FlowInterface $flow)
    {
    }

    public function __invoke(Ip $ip, Closure $callback = null): void
    {
        ($this->flow)($ip, $callback);
    }

    public function fn(array|Closure|FlowInterface $flow): FlowInterface
    {
        return $this->flow->fn($flow);
    }

    public static function do(callable $callable, ?array $config = null): FlowInterface
    {
        return Flow::do($callable, $config);
    }
}
