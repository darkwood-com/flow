<?php

declare(strict_types=1);

namespace Flow;

use Closure;
use Generator;

/**
 * @template T1
 */
interface FlowInterface
{
    /**
     * @param Ip<T1>                     $ip
     * @param null|Closure(Ip<T1>): void $callback
     */
    public function __invoke(Ip $ip, Closure $callback = null): void;

    /**
     * @template T2
     *
     * @param array<mixed>|Closure|FlowInterface<T2> $flow can be Closure as Job, array constructor arguments for Flow instanciation, array configuration for Flow instanciation or FlowInterface instance
     *                                                     #param ?array{
     *                                                     0: Closure|array,
     *                                                     1?: Closure|array,
     *                                                     2?: IpStrategyInterface,
     *                                                     3?: DriverInterface
     *                                                     }|array{
     *                                                     "jobs"?: Closure|array,
     *                                                     "errorJobs"?: Closure|array,
     *                                                     "ipStrategy"?: IpStrategyInterface,
     *                                                     "driver"?: DriverInterface
     *                                                     }|Closure|FlowInterface<T2> $config
     *
     * @return FlowInterface<T1>
     */
    public function fn(array|Closure|self $flow): self;

    /**
     * Do-notation a.k.a. for-comprehension.
     *
     * Syntax sugar for sequential {@see FlowInterface::fn()} calls
     *
     * Syntax "$flow = yield $wrapedFlow" mean:
     * 1) $wrapedFlow can be Closure as Job, array constructor arguments for Flow instanciation, array configuration for Flow instanciation or FlowInterface instance
     * 2) $flow is assigned as FlowInterface instance
     * 3) optionnaly you can return another wrapedFlow
     *
     * ```php
     * $flow = Flow::do(static function() {
     *     yield new Flow(fn($a) => $a + 1);
     *     $flow = yield fn($b) => $b * 2;
     *     $flow = yield $flow->fn([fn($c) => $c * 4])
     *     return [$flow, [fn($d) => $d - 8]];
     * });
     * ```
     * $config if provided will be the fallback array configuration for Flow instanciation
     *
     * @param callable(): Generator|Closure $callable
     * @param ?array<mixed>                 $config
     *
     * #param ?array{
     *  0: Closure|array,
     *  1?: Closure|array,
     *  2?: IpStrategyInterface<mixed>,
     *  3?: DriverInterface
     * }|array{
     *  "jobs"?: Closure|array,
     *  "errorJobs"?: Closure|array,
     *  "ipStrategy"?: IpStrategyInterface<mixed>,
     *  "driver"?: DriverInterface
     * } $config
     *
     * @return FlowInterface<mixed>
     */
    public static function do(callable $callable, ?array $config = null): self;
}
