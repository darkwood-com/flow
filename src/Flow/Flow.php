<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\Driver\FiberDriver;
use Flow\DriverInterface;
use Flow\Exception\LogicException;
use Flow\ExceptionInterface;
use Flow\FlowInterface;
use Flow\Ip;
use Flow\IpStrategy\LinearIpStrategy;
use Flow\IpStrategyInterface;
use Generator;
use SplObjectStorage;

use function array_key_exists;
use function count;
use function is_array;

/**
 * @template T1
 * @template T2
 *
 * @implements FlowInterface<T1>
 */
class Flow implements FlowInterface
{
    /**
     * @var array<Closure(T1): T2>
     */
    private array $jobs;

    /**
     * @var array<Closure(Ip<T1>, ExceptionInterface): void>
     */
    private array $errorJobs;

    /**
     * @var IpStrategyInterface<T1>
     */
    private IpStrategyInterface $ipStrategy;

    /**
     * @var DriverInterface<T1,T2>
     */
    private DriverInterface $driver;

    /**
     * @var SplObjectStorage<Ip<T1>, null|Closure(Ip<T1>): void>
     */
    private SplObjectStorage $callbacks;

    /**
     * @var null|FlowInterface<T2>
     */
    private ?FlowInterface $fnFlow = null;

    /**
     * @param array<Closure(T1): T2>|Closure(T1): T2                                                     $jobs
     * @param array<Closure(Ip<T1>, ExceptionInterface): void>|Closure(Ip<T1>, ExceptionInterface): void $errorJobs
     * @param null|IpStrategyInterface<T1>                                                               $ipStrategy
     * @param null|DriverInterface<T1,T2>                                                                $driver
     */
    public function __construct(
        Closure|array $jobs,
        Closure|array $errorJobs = null,
        IpStrategyInterface $ipStrategy = null,
        DriverInterface $driver = null
    ) {
        $this->jobs = is_array($jobs) ? $jobs : [$jobs];
        $this->errorJobs = $errorJobs ? (is_array($errorJobs) ? $errorJobs : [$errorJobs]) : [];
        $this->ipStrategy = $ipStrategy ?? new LinearIpStrategy();
        $this->driver = $driver ?? new FiberDriver();
        $this->callbacks = new SplObjectStorage();
    }

    public function __invoke(Ip $ip, Closure $callback = null): void
    {
        $this->callbacks->offsetSet($ip, $callback);
        $this->ipStrategy->push($ip);
        $this->nextIpJob();
    }

    public static function do(callable $callable, ?array $config = null): FlowInterface
    {
        /**
         * @var Closure|Generator $generator
         */
        $generator = $callable();

        if ($generator instanceof Generator) {
            $flows = [];

            while ($generator->valid()) {
                $flow = self::flowUnwrap($generator->current(), $config);

                $generator->send($flow);

                $flows[] = $flow;
            }

            $return = $generator->getReturn();
            if (!empty($return)) {
                $flows[] = self::flowUnwrap($return, $config);
            }

            return self::flowMap($flows);
        }

        return self::flowUnwrap($generator, $config);
    }

    public function fn(array|Closure|FlowInterface $flow): FlowInterface
    {
        $flow = self::flowUnwrap($flow);

        if ($this->fnFlow) {
            $this->fnFlow->fn($flow);
        } else {
            $this->fnFlow = $flow;
        }

        return $this;
    }

    private function nextIpJob(): void
    {
        $ip = $this->ipStrategy->pop();
        if (!$ip) {
            return;
        }

        $callback = $this->callbacks->offsetGet($ip);
        $this->callbacks->offsetUnset($ip);

        $count = count($this->jobs);
        foreach ($this->jobs as $i => $job) {
            $this->driver->async($job, function ($value) use ($ip, &$count, $i, $callback) {
                $count--;
                if ($count === 0 || $value instanceof ExceptionInterface) {
                    $count = 0;
                    $this->ipStrategy->done($ip);
                    $this->nextIpJob();

                    if ($value instanceof ExceptionInterface) {
                        if (isset($this->errorJobs[$i])) {
                            $this->errorJobs[$i]($ip, $value);
                        } else {
                            throw $value;
                        }
                    }

                    if ($this->fnFlow) {
                        ($this->fnFlow)($ip, $callback);
                    } else {
                        ($callback)($ip);
                    }
                }
            })($ip->data);
        }
    }

    /**
     * @template TI
     *
     * @param array<mixed>|Closure|FlowInterface<TI> $flow
     * @param ?array<mixed>                          $config
     *
     * @return FlowInterface<mixed>
     *
     * #param ?array{
     *  0: Closure|array,
     *  1?: Closure|array,
     *  2?: IpStrategyInterface
     *  3?: DriverInterface
     * }|array{
     *  "jobs"?: Closure|array,
     *  "errorJobs"?: Closure|array,
     *  "ipStrategy"?: IpStrategyInterface
     *  "driver"?: DriverInterface
     * } $config
     */
    private static function flowUnwrap($flow, ?array $config = null): FlowInterface
    {
        if ($flow instanceof Closure) {
            return new self(...[...['jobs' => $flow], ...($config ?? [])]);
        }
        if (is_array($flow)) {
            if (array_key_exists(0, $flow) || array_key_exists('jobs', $flow)) {
                return new self(...[...$flow, ...($config ?? [])]);
            }

            return self::flowMap($flow);
        }

        return $flow;
    }

    /**
     * @param array<FlowInterface<mixed>> $flows
     *
     * @return FlowInterface<mixed>
     */
    private static function flowMap(array $flows)
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
