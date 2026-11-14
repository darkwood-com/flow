<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Flow\AsyncHandler\AsyncHandler;
use Flow\AsyncHandlerInterface;
use Flow\Driver\FiberDriver;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\PopEvent;
use Flow\Event\PushEvent;
use Flow\Exception\RuntimeException;
use Flow\ExceptionInterface;
use Flow\FlowFactory;
use Flow\FlowInterface;
use Flow\Ip;
use Flow\IpStrategy\LinearIpStrategy;
use Flow\IpStrategy\MaxIpStrategy;
use Flow\IpStrategyInterface;
use Flow\JobInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

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
     * @var IpStrategyInterface<T1>
     */
    private IpStrategyInterface $ipStrategy;

    private ?Throwable $failure = null;

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
        $this->ipStrategy = $ipStrategy ?? new LinearIpStrategy();
        $this->dispatcher->addSubscriber($this->ipStrategy);
        $this->dispatcher->addSubscriber($asyncHandler ?? new AsyncHandler());
        $this->stream['dispatchers'][] = $this->dispatcher;
        $this->stream['releases'][] = $this->releaseIp(...);
        $this->stream['complete'] = $this->completeIp(...);
        $this->stream['failIp'] = $this->failIp(...);
        $this->stream['reportFailure'] = $this->reportFailure(...);
        $this->stream['hasFailure'] = fn (): bool => $this->failure !== null;
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
        $this->stream['releases'][] = $flow->releaseIp(...);

        return $this;
    }

    public function await(): void
    {
        try {
            $this->driver->await($this->stream);
        } catch (Throwable $exception) {
            $this->reportFailure($exception);
        }

        if ($this->failure !== null) {
            $failure = $this->failure;
            $this->failure = null;

            throw $failure;
        }
    }

    /**
     * @param Ip<mixed> $ip
     */
    private function completeIp(int $index, Ip $ip, mixed $data): void
    {
        if ($this->failure === null) {
            try {
                if ($data instanceof RuntimeException && $this->stream['fnFlows'][$index]['errorJob'] !== null) {
                    $this->stream['fnFlows'][$index]['errorJob']($data);
                } elseif (isset($this->stream['fnFlows'][$index + 1])) {
                    $this->stream['dispatchers'][$index + 1]->dispatch(new PushEvent(new Ip($data)), Event::PUSH);
                }
            } catch (Throwable $exception) {
                $this->reportFailure($exception);
            }
        }

        $this->finishIp($index, $ip);
    }

    /**
     * @param Ip<mixed> $ip
     */
    private function failIp(int $index, Ip $ip, Throwable $exception): void
    {
        $this->reportFailure($exception);
        $this->finishIp($index, $ip);
    }

    /**
     * @param Ip<mixed> $ip
     */
    private function finishIp(int $index, Ip $ip): void
    {
        try {
            ($this->stream['releases'][$index])();
        } catch (Throwable $exception) {
            $this->reportFailure($exception);
        }

        try {
            $this->stream['dispatchers'][$index]->dispatch(new PopEvent($ip), Event::POP);
        } catch (Throwable $exception) {
            $this->reportFailure($exception);
        }
    }

    private function releaseIp(): void
    {
        if ($this->ipStrategy instanceof MaxIpStrategy) {
            $this->ipStrategy->release();
        }
    }

    private function reportFailure(Throwable $exception): void
    {
        $this->failure ??= $exception;
    }
}
