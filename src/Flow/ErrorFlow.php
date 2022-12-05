<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\DriverInterface;
use Flow\Ip;
use Flow\IpStrategyInterface;
use Flow\FlowInterface;
use Throwable;

class ErrorFlow implements FlowInterface
{
    private Flow $errorFlow;

    public function __construct(private FlowInterface $flow, Closure $job, ?IpStrategyInterface $ipStrategy = null, ?DriverInterface $driver = null)
    {
        $this->errorFlow = new Flow($job, $ipStrategy, $driver);
    }

    public function __invoke(Ip $ip, mixed $context = null): void
    {
        ($this->flow)($ip, $context);
    }

    public function pipe(Closure $callback): void
    {
        $this->flow->pipe(function (Ip $ip, Throwable $exception = null) use ($callback) {
            if ($exception) {
                ($this->errorFlow)($ip, $exception);
            } else {
                $callback($ip, null);
            }
        });
        $this->errorFlow->pipe($callback);
    }
}
