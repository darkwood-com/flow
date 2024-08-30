<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\AsyncHandlerInterface;
use Flow\DriverInterface;
use Flow\ExceptionInterface;
use Flow\IpStrategyInterface;
use Flow\Job\YJob;
use Flow\JobInterface;
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
     * @param null|Closure(ExceptionInterface): void|JobInterface<ExceptionInterface,void> $errorJob
     * @param null|IpStrategyInterface<T1>                                                 $ipStrategy
     * @param null|AsyncHandlerInterface<T1>                                               $asyncHandler
     * @param null|DriverInterface<T1,T2>                                                  $driver
     */
    public function __construct(
        Closure|JobInterface $job,
        null|Closure|JobInterface $errorJob = null,
        ?IpStrategyInterface $ipStrategy = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?AsyncHandlerInterface $asyncHandler = null,
        ?DriverInterface $driver = null
    ) {
        parent::__construct(new YJob($job), $errorJob, $ipStrategy, $dispatcher, $asyncHandler, $driver);
    }
}
