<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\DriverInterface;
use Flow\ExceptionInterface;
use Flow\Ip;
use Flow\IpStrategyInterface;

/**
 * @template T1
 * @template T2
 *
 * @extends FlowDecorator<T1,T2>
 */
class YFlow extends FlowDecorator
{
    /**
     * @param null|Closure(Ip<T1>, ExceptionInterface): void $errorJob
     * @param null|IpStrategyInterface<T1>                   $ipStrategy
     * @param null|DriverInterface<T1,T2>                    $driver
     */
    public function __construct(Closure $job, Closure $errorJob = null, IpStrategyInterface $ipStrategy = null, DriverInterface $driver = null)
    {
        $U = static fn (Closure $f) => $f($f);
        $Y = static fn (Closure $f) => $U(static fn (Closure $x) => $f(static fn ($y) => $U($x)($y)));

        parent::__construct(new Flow($Y($job), $errorJob, $ipStrategy, $driver));
    }
}
