<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use loophp\combinator\Combinators;
use Flow\DriverInterface;
use Flow\IpStrategyInterface;

class LambdaFlow extends FlowDecorator
{
    public function __construct(Closure $job, ?IpStrategyInterface $ipStrategy = null, ?DriverInterface $driver = null)
    {
        parent::__construct(new Flow(Combinators::Y()($job), $ipStrategy, $driver));
    }
}
