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

    public function __construct(
        private Closure $job,
        private ?IpStrategyInterface $ipStrategy = null,
        private ?DriverInterface $driver = null
    ) {
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

        $this->driver->coroutine($this->job, function (Throwable $exception = null) use ($ip) {
            $this->ipStrategy->done($ip);
            $this->nextIpJob();

            if ($this->pipeCallback) {
                ($this->pipeCallback)($ip, $exception);
            }
        })($ip->getData(), $context);
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
