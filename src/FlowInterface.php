<?php

declare(strict_types=1);

namespace Flow;

use Closure;

/**
 * @template T1
 */
interface FlowInterface
{
    /**
     * @param Ip<T1> $ip
     */
    public function __invoke(Ip $ip): void;

    /**
     * @template T2
     *
     * @param array<mixed>|Closure(T1): T2|FlowInterface<T2>|JobInterface<T1,T2> $flow can be Closure as Job, array constructor arguments for Flow instanciation, array configuration for Flow instanciation or FlowInterface instance
     *                                                                                 #param ?array{
     *                                                                                 0: Closure,
     *                                                                                 1?: Closure,
     *                                                                                 2?: IpStrategyInterface,
     *                                                                                 3?: DriverInterface
     *                                                                                 }|array{
     *                                                                                 "job"?: JobInterface|Closure,
     *                                                                                 "errorJob"?: JobInterface|Closure,
     *                                                                                 "ipStrategy"?: IpStrategyInterface,
     *                                                                                 "driver"?: DriverInterface
     *                                                                                 }|Closure|FlowInterface<T2> $config
     *
     * @return FlowInterface<T1>
     */
    public function fn(array|Closure|JobInterface|self $flow): self;

    /**
     * Await asynchonous call for current IPs.
     * After await, all IPs have been proceed, it continues synchronously.
     */
    public function await(): void;
}
