<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\DriverInterface;
use Flow\IpStrategyInterface;

class YFlow extends FlowDecorator
{
    public function __construct(Closure $job, Closure $errorJob = null, IpStrategyInterface $ipStrategy = null, DriverInterface $driver = null)
    {
        $U = static fn (Closure $f) => $f($f);
        $Y = static fn (Closure $f) => $U(static fn (Closure $x) => $f(static fn ($y) => $U($x)($y)));

        parent::__construct(new Flow($Y($job), $errorJob, $ipStrategy, $driver));
    }
}
