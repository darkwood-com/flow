<?php

declare(strict_types=1);

namespace RFBP\Rail;

use Closure;
use RFBP\DriverInterface;
use RFBP\Ip;
use RFBP\IpStrategyInterface;
use RFBP\RailInterface;
use Throwable;

class ErrorRail implements RailInterface
{
    private Rail $errorRail;

    public function __construct(private RailInterface $rail, Closure $job, ?IpStrategyInterface $ipStrategy = null, ?DriverInterface $driver = null)
    {
        $this->errorRail = new Rail($job, $ipStrategy, $driver);
    }

    public function __invoke(Ip $ip, mixed $context = null): void
    {
        ($this->rail)($ip, $context);
    }

    public function pipe(Closure $callback): void
    {
        $this->rail->pipe(function ($ip, Throwable $exception = null) use ($callback) {
            if ($exception) {
                ($this->errorRail)($ip, $exception);
            } else {
                $callback($ip, null);
            }
        });
        $this->errorRail->pipe($callback);
    }
}
