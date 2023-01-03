<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\DriverInterface;
use Flow\IpStrategyInterface;

class YFlow extends FlowDecorator
{
    public function __construct(Closure $job, Closure $errorJob, ?IpStrategyInterface $ipStrategy = null, ?DriverInterface $driver = null)
    {
        $U = fn (Closure $f) => $f($f);
        $Y = fn (Closure $f) => $U(fn (Closure $x) => $f(fn ($y) => $U($x)($y)));

        parent::__construct(new Flow($Y($job), $errorJob, $ipStrategy, $driver));
    }
}
