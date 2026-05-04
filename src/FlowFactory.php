<?php

declare(strict_types=1);

namespace Flow;

use Closure;
use Flow\Exception\LogicException;
use Flow\Flow\Flow;
use Generator;

use function array_key_exists;
use function is_array;

class FlowFactory
{
    /**
     * @param null|DriverInterface<mixed,mixed> $driver
     */
    public function __construct(
        private ?DriverInterface $driver = null
    ) {}

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
     * $flow = (new FlowFactory())->create(static function() {
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
     *  3?: EventDispatcherInterface,
     *  4?: AsyncHandlerInterface,
     *  5?: DriverInterface
     * }|array{
     *  "jobs"?: JobInterface|Closure|array,
     *  "errorJobs"?: JobInterface|Closure|array,
     *  "ipStrategy"?: IpStrategyInterface<mixed>,
     *  "dispatcher"?: EventDispatcherInterface,
     *  "asyncHandler"?: AsyncHandlerInterface,
     *  "driver"?: DriverInterface
     * } $config
     *
     * @return FlowInterface<mixed>
     */
    public function create(callable $callable, ?array $config = null): FlowInterface
    {
        /**
         * @var Closure|Generator $generator
         */
        $generator = $callable();

        if ($generator instanceof Generator) {
            return $this->createFromGenerator($generator, $config);
        }

        return $this->createFlow($generator, $config);
    }

    /**
     * @param array<mixed>|Closure|FlowInterface<mixed> $flow
     * @param ?array<mixed>                             $config
     *
     * @return Flow<mixed, mixed>
     *
     * #param ?array{
     *  0: Closure,
     *  1?: Closure,
     *  2?: IpStrategyInterface,
     *  3?: EventDispatcherInterface,
     *  4?: AsyncHandlerInterface,
     *  5?: DriverInterface
     * }|array{
     *  "ipStrategy"?: IpStrategyInterface,
     *  "dispatcher"?: EventDispatcherInterface,
     *  "asyncHandler"?: AsyncHandlerInterface,
     *  "driver"?: DriverInterface
     * } $config
     */
    public function createFlow($flow, ?array $config = null): Flow
    {
        if ($flow instanceof Closure || $flow instanceof JobInterface) {
            return new Flow(...[...['job' => $flow, 'driver' => $this->driver], ...($config ?? [])]);
        }
        if (is_array($flow)) {
            if (array_key_exists(0, $flow) || array_key_exists('job', $flow)) {
                return new Flow(...[...$flow, ...['driver' => $this->driver], ...($config ?? [])]);
            }

            return $this->createFlowMap($flow);
        }

        return $flow;
    }

    /**
     * @param ?array<mixed> $config
     *
     * @return FlowInterface<mixed>
     */
    private function createFromGenerator(Generator $generator, ?array $config = null): FlowInterface
    {
        $flows = [];

        while ($generator->valid()) {
            $flow = $this->createFlow($generator->current(), $config);
            $generator->send($flow);
            $flows[] = $flow;
        }

        $return = $generator->getReturn();
        if (!empty($return)) {
            $flows[] = $this->createFlow($return, $config);
        }

        return $this->createFlowMap($flows);
    }

    /**
     * @param array<Flow<mixed, mixed>> $flows
     *
     * @return Flow<mixed, mixed>
     */
    private function createFlowMap(array $flows): Flow
    {
        $flow = array_shift($flows);
        if (null === $flow) {
            throw new LogicException('Flow is empty');
        }

        foreach ($flows as $flowIt) {
            $flow = $flow->fn($flowIt);
        }

        return $flow;
    }
}
