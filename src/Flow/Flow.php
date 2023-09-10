<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\Driver\ReactDriver;
use Flow\DriverInterface;
use Flow\ExceptionInterface;
use Flow\FlowInterface;
use Flow\Ip;
use Flow\IpStrategy\LinearIpStrategy;
use Flow\IpStrategyInterface;
use SplObjectStorage;

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
        $this->driver = $driver ?? new ReactDriver();
        $this->callbacks = new SplObjectStorage();
    }

    public function __invoke(Ip $ip, Closure $callback = null): void
    {
        $this->callbacks->offsetSet($ip, $callback);
        $this->ipStrategy->push($ip);
        $this->nextIpJob();
    }

    /**
     * @param FlowInterface<T2> $flow
     *
     * @return FlowInterface<T1>
     */
    public function fn(FlowInterface $flow): FlowInterface
    {
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

                        return;
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
}
