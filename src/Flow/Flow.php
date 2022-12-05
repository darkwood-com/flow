<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use LogicException;
use Flow\Driver\AmpDriver;
use Flow\DriverInterface;
use Flow\Ip;
use Flow\IpStrategy\LinearIpStrategy;
use Flow\IpStrategyInterface;
use Flow\FlowInterface;
use SplObjectStorage;
use Throwable;

class Flow implements FlowInterface
{
    /**
     * @var array<Closure>
     */
    private array $jobs;

    private IpStrategyInterface $ipStrategy;

    private DriverInterface $driver;

    /**
     * @var SplObjectStorage<Ip, mixed>
     */
    private SplObjectStorage $contexts;
    private ?Closure $pipeCallback = null;

    /**
     * @param Closure|array<Closure> $jobs
     */
    public function __construct(
        Closure|array $jobs,
        IpStrategyInterface $ipStrategy = null,
        DriverInterface $driver = null
    ) {
        $this->jobs = is_array($jobs) ? $jobs : [$jobs];
        $this->ipStrategy = $ipStrategy ?? new LinearIpStrategy();
        $this->driver = $driver ?? new AmpDriver();
        $this->contexts = new SplObjectStorage();
    }

    private function nextIpJob(): void
    {
        $ip = $this->ipStrategy->pop();
        if (!$ip) {
            return;
        }

        $context = $this->contexts->offsetGet($ip);
        $this->contexts->offsetUnset($ip);

        $count = count($this->jobs);
        foreach ($this->jobs as $job) {
            $this->driver->coroutine($job, function (Throwable $exception = null) use ($ip, &$count) {
                $count--;
                if($count === 0 || ($exception && $count >= 0)) {
                    $count = 0;
                    $this->ipStrategy->done($ip);
                    $this->nextIpJob();

                    if ($this->pipeCallback) {
                        ($this->pipeCallback)($ip, $exception);
                    }
                }
            })($ip->getData(), $context);
        }
    }

    public function __invoke(Ip $ip, mixed $context = null): void
    {
        $this->contexts->offsetSet($ip, $context);
        $this->ipStrategy->push($ip);
        $this->nextIpJob();
    }

    public function pipe(Closure $callback): void
    {
        if ($this->pipeCallback) {
            throw new LogicException('Callback is already set');
        }

        $this->pipeCallback = $callback;
    }
}
