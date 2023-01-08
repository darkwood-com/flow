<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\Driver\ReactDriver;
use Flow\DriverInterface;
use Flow\FlowInterface;
use Flow\Ip;
use Flow\IpStrategy\LinearIpStrategy;
use Flow\IpStrategyInterface;
use SplObjectStorage;
use Throwable;

class Flow implements FlowInterface
{
    /**
     * @var array<Closure>
     */
    private array $jobs;

    /**
     * @var array<Closure>
     */
    private array $errorJobs;

    private IpStrategyInterface $ipStrategy;

    private DriverInterface $driver;

    /**
     * @var SplObjectStorage<Ip, mixed>
     */
    private SplObjectStorage $callbacks;
    private ?FlowInterface $fnFlow = null;

    /**
     * @param Closure|array<Closure> $jobs
     * @param Closure|array<Closure> $errorJobs
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
            $this->driver->async($job, function (Throwable $exception = null) use ($ip, &$count, $i, $callback) {
                $count--;
                if ($count === 0 || $exception !== null) {
                    $count = 0;
                    $this->ipStrategy->done($ip);
                    $this->nextIpJob();

                    if ($exception) {
                        if (isset($this->errorJobs[$i])) {
                            $this->errorJobs[$i]($ip->data, $exception);
                        } else {
                            throw $exception;
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

    public function __invoke(Ip $ip, ?Closure $callback = null): void
    {
        $this->callbacks->offsetSet($ip, $callback);
        $this->ipStrategy->push($ip);
        $this->nextIpJob();
    }

    public function fn(FlowInterface $flow): FlowInterface
    {
        if ($this->fnFlow) {
            $this->fnFlow->fn($flow);
        } else {
            $this->fnFlow = $flow;
        }

        return $this;
    }
}
