<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\AsyncHandlerInterface;
use Flow\DriverInterface;
use Flow\ExceptionInterface;
use Flow\IpStrategyInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @template T1
 * @template T2
 *
 * @extends Flow<T1,T2>
 */
class YFlow extends Flow
{
    /**
     * @param null|Closure(ExceptionInterface): void $errorJob
     * @param null|IpStrategyInterface<T1>           $ipStrategy
     * @param null|AsyncHandlerInterface<T1>         $asyncHandler
     * @param null|DriverInterface<T1,T2>            $driver
     */
    public function __construct(
        Closure $job,
        ?Closure $errorJob = null,
        ?IpStrategyInterface $ipStrategy = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?AsyncHandlerInterface $asyncHandler = null,
        ?DriverInterface $driver = null
    ) {
        $U = static fn (Closure $f) => $f($f);
        $Y = static fn (Closure $f) => $U(static fn (Closure $x) => $f(static fn ($y) => $U($x)($y)));

        parent::__construct($Y($job), $errorJob, $ipStrategy, $dispatcher, $asyncHandler, $driver);
    }
}
