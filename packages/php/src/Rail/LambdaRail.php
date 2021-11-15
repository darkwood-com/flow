<?php

declare(strict_types=1);

namespace RFBP\Rail;

use Closure;
use loophp\combinator\Combinators;
use RFBP\DriverInterface;
use RFBP\IpStrategyInterface;

class LambdaRail extends RailDecorator
{
    public function __construct(Closure $job, ?IpStrategyInterface $ipStrategy = null, ?DriverInterface $driver = null)
    {
        parent::__construct(new Rail(Combinators::Y()($job), $ipStrategy, $driver));
    }
}
