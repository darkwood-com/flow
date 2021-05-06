<?php

declare(strict_types=1);

namespace RFBP\Rail;

use Closure;
use LogicException;
use RFBP\Driver\AmpDriver;
use RFBP\DriverInterface;
use RFBP\Ip;
use RFBP\IpStrategy\LinearIpStrategy;
use RFBP\IpStrategyInterface;
use RFBP\RailInterface;
use SplObjectStorage;
use Throwable;

class Rail implements RailInterface
{
    /**
     * @var SplObjectStorage<Ip, mixed>
     */
    private SplObjectStorage $contexts;
    private ?Closure $pipeCallback = null;

    /**
     * @param Closure|array<Closure> $jobs
     */
    public function __construct(
        private Closure|array $jobs,
        private ?IpStrategyInterface $ipStrategy = null,
        private ?DriverInterface $driver = null
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
