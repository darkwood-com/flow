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
use Flow\Exception\LogicException;
use Flow\ExceptionInterface;
use Flow\FlowInterface;
use Flow\Ip;
use Flow\IpStrategy\LinearIpStrategy;
use Flow\IpStrategyInterface;
use Flow\JobInterface;
use Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_key_exists;
use function is_array;

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
        null|Closure|JobInterface $errorJob = null,
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

    public static function do(callable $callable, ?array $config = null): FlowInterface
    {
        /**
         * @var Closure|Generator $generator
         */
        $generator = $callable();

        if ($generator instanceof Generator) {
            $flows = [];

            while ($generator->valid()) {
                $flow = self::flowUnwrap($generator->current(), $config);

                $generator->send($flow);

                $flows[] = $flow;
            }

            $return = $generator->getReturn();
            if (!empty($return)) {
                $flows[] = self::flowUnwrap($return, $config);
            }

            return self::flowMap($flows);
        }

        return self::flowUnwrap($generator, $config);
    }

    public function fn(array|Closure|FlowInterface|JobInterface $flow): FlowInterface
    {
        $flow = self::flowUnwrap($flow, ['driver' => $this->driver]);

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

    /**
     * @param array<mixed>|Closure|FlowInterface<mixed> $flow
     * @param ?array<mixed>                             $config
     *
     * @return Flow<mixed, mixed>
     *
     * #param ?array{
     *  0: Closure,
     *  1?: Closure,
     *  2?: IpStrategyInterface,
     *  3?: EventDispatcherInterface,
     *  4?: AsyncHandlerInterface,
     *  5?: DriverInterface
     * }|array{
     *  "ipStrategy"?: IpStrategyInterface,
     *  "dispatcher"?: EventDispatcherInterface,
     *  "asyncHandler"?: AsyncHandlerInterface,
     *  "driver"?: DriverInterface
     * } $config
     */
    private static function flowUnwrap($flow, ?array $config = null): FlowInterface
    {
        if ($flow instanceof Closure || $flow instanceof JobInterface) {
            return new self(...[...['job' => $flow], ...($config ?? [])]);
        }
        if (is_array($flow)) {
            if (array_key_exists(0, $flow) || array_key_exists('job', $flow)) {
                return new self(...[...$flow, ...($config ?? [])]);
            }

            return self::flowMap($flow);
        }

        return $flow;
    }

    /**
     * @param array<FlowInterface<mixed>> $flows
     *
     * @return FlowInterface<mixed>
     */
    private static function flowMap(array $flows)
    {
        $flow = array_shift($flows);
        if (null === $flow) {
            throw new LogicException('Flow is empty');
        }

        foreach ($flows as $flowIt) {
            $flow = $flow->fn($flowIt);
        }

        return $flow;
    }
}
