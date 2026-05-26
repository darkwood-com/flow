<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\AsyncHandler\AsyncHandler;
use Flow\AsyncHandlerInterface;
use Flow\Driver\FiberDriver;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\PushEvent;
use Flow\ExceptionInterface;
use Flow\FlowFactory;
use Flow\FlowInterface;
use Flow\Ip;
use Flow\IpStrategy\LinearIpStrategy;
use Flow\IpStrategyInterface;
use Flow\JobInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @template T1
 * @template T2
 *
 * @implements FlowInterface<T1>
 */
class Flow implements FlowInterface
{
    /**
     * @var array<mixed>
     */
    private array $stream = [
        'fnFlows' => [],
        'dispatchers' => [],
    ];

    /**
     * @var Closure(T1): T2|JobInterface<T1,T2>
     */
    private $job;

    /**
     * @var null|Closure(ExceptionInterface): void|JobInterface<ExceptionInterface,void>
     */
    private $errorJob;

    private EventDispatcherInterface $dispatcher;

    /**
     * @var DriverInterface<T1,T2>
     */
    private DriverInterface $driver;

    /**
     * @param Closure(T1): T2|JobInterface<T1,T2>                                          $job
     * @param null|Closure(ExceptionInterface): void|JobInterface<ExceptionInterface,void> $errorJob
     * @param null|IpStrategyInterface<T1>                                                 $ipStrategy
     * @param null|AsyncHandlerInterface<T1>                                               $asyncHandler
     * @param null|DriverInterface<T1,T2>                                                  $driver
     */
    public function __construct(
        Closure|JobInterface $job,
        Closure|JobInterface|null $errorJob = null,
        ?IpStrategyInterface $ipStrategy = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?AsyncHandlerInterface $asyncHandler = null,
        ?DriverInterface $driver = null,
    ) {
        $this->job = $job;
        $this->errorJob = $errorJob;
        $this->stream['fnFlows'][] = [
            'job' => $this->job,
            'errorJob' => $this->errorJob,
        ];
        $this->dispatcher = $dispatcher ?? new EventDispatcher();
        $this->dispatcher->addSubscriber($ipStrategy ?? new LinearIpStrategy());
        $this->dispatcher->addSubscriber($asyncHandler ?? new AsyncHandler());
        $this->stream['dispatchers'][] = $this->dispatcher;
        $this->driver = $driver ?? new FiberDriver();
    }

    public function __invoke(Ip $ip): void
    {
        $this->stream['dispatchers'][0]->dispatch(new PushEvent($ip), Event::PUSH);
    }

    public function fn(array|Closure|FlowInterface|JobInterface $flow): FlowInterface
    {
        $flow = (new FlowFactory($this->driver))->createFlow($flow);

        $this->stream['fnFlows'][] = [
            'job' => $flow->job,
            'errorJob' => $flow->errorJob,
        ];
        $this->stream['dispatchers'][] = $flow->dispatcher;

        return $this;
    }

    public function await(): void
    {
        $this->driver->await($this->stream);
    }
}
