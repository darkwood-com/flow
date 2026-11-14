<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use ArrayObject;
use Flow\AsyncHandler\AsyncHandler;
use Flow\AsyncHandler\BatchAsyncHandler;
use Flow\AsyncHandler\DeferAsyncHandler;
use Flow\Driver\AmpDriver;
use Flow\Driver\FiberDriver;
use Flow\Driver\ReactDriver;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\PopEvent;
use Flow\Exception\RuntimeException as FlowRuntimeException;
use Flow\ExceptionInterface;
use Flow\Flow\Flow;
use Flow\FlowFactory;
use Flow\Ip;
use Flow\IpStrategy\LinearIpStrategy;
use Flow\IpStrategy\MaxIpStrategy;
use Flow\IpStrategy\StackIpStrategy;
use Flow\IpStrategyInterface;
use Flow\Job\ClosureJob;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @template T1
 * @template T2
 */
class FlowTest extends TestCase
{
    use FlowTrait;

    public function testBatchAndMaxStrategyDrainTheFinalIncompleteBatch(): void
    {
        $driver = new FiberDriver();
        $results = [];
        $flow = new Flow(
            static fn (int $value): int => $value * 2,
            null,
            new MaxIpStrategy(2),
            null,
            new BatchAsyncHandler(2),
            $driver,
        );
        $flow->fn(static function (int $value) use (&$results): void {
            $results[] = $value;
        });

        $flow(new Ip(1));
        $flow(new Ip(2));
        $flow(new Ip(3));
        $flow->await();

        sort($results);
        self::assertSame([2, 4, 6], $results);
    }

    public function testErrorJobFailurePopsOnceAndDoesNotBlockTheNextRun(): void
    {
        $driver = new FiberDriver();
        $dispatcher = new EventDispatcher();
        $popCount = 0;
        $dispatcher->addListener(Event::POP, static function (PopEvent $event) use (&$popCount): void {
            $popCount++;
        });
        $results = [];
        $flow = new Flow(
            static function (string $value): string {
                if ('bad' === $value) {
                    throw new RuntimeException('job failed');
                }

                return $value;
            },
            static function (): never {
                throw new RuntimeException('error handler failed');
            },
            new MaxIpStrategy(1),
            $dispatcher,
            new AsyncHandler(),
            $driver,
        );
        $flow->fn(static function (string $value) use (&$results): void {
            $results[] = $value;
        });
        $flow(new Ip('bad'));
        $flow(new Ip('good'));

        try {
            $flow->await();
            self::fail('The error handler exception was not propagated.');
        } catch (RuntimeException $exception) {
            self::assertSame('error handler failed', $exception->getMessage());
        }

        self::assertSame(1, $popCount);
        $flow->await();
        self::assertSame(['good'], $results);
        self::assertSame(2, $popCount);
    }

    public function testDownstreamListenerFailureDoesNotBlockTheNextRun(): void
    {
        $driver = new FiberDriver();
        $rootDispatcher = new EventDispatcher();
        $popCount = 0;
        $rootDispatcher->addListener(Event::POP, static function () use (&$popCount): void {
            $popCount++;
        });

        $downstreamDispatcher = new EventDispatcher();
        $fail = true;
        $downstreamDispatcher->addListener(Event::PUSH, static function () use (&$fail): void {
            if ($fail) {
                $fail = false;

                throw new LogicException('listener failed');
            }
        });
        $results = [];
        $downstream = new Flow(
            static function (string $value) use (&$results): void {
                $results[] = $value;
            },
            null,
            null,
            $downstreamDispatcher,
            null,
            $driver,
        );
        $flow = new Flow(
            static fn (string $value): string => $value,
            null,
            new MaxIpStrategy(1),
            $rootDispatcher,
            null,
            $driver,
        );
        $flow->fn($downstream);
        $flow(new Ip('first'));
        $flow(new Ip('second'));

        try {
            $flow->await();
            self::fail('The listener exception was not propagated.');
        } catch (LogicException $exception) {
            self::assertSame('listener failed', $exception->getMessage());
        }

        self::assertSame(1, $popCount);
        $flow->await();
        self::assertSame(['second'], $results);
        self::assertSame(2, $popCount);
    }

    public function testFailingPopListenerCannotKeepMaxStrategyAtCapacity(): void
    {
        $driver = new FiberDriver();
        $dispatcher = new EventDispatcher();
        $fail = true;
        $dispatcher->addListener(Event::POP, static function () use (&$fail): void {
            if ($fail) {
                $fail = false;

                throw new LogicException('pop listener failed');
            }
        });
        $results = [];
        $flow = new Flow(
            static function (string $value) use (&$results): void {
                $results[] = $value;
            },
            null,
            new MaxIpStrategy(1),
            $dispatcher,
            null,
            $driver,
        );
        $flow(new Ip('first'));
        $flow(new Ip('second'));

        try {
            $flow->await();
            self::fail('The POP listener exception was not propagated.');
        } catch (LogicException $exception) {
            self::assertSame('pop listener failed', $exception->getMessage());
        }

        $flow->await();
        self::assertSame(['first', 'second'], $results);
    }

    public function testJobErrorsWithoutAnErrorJobRemainHistoricalValues(): void
    {
        $received = null;
        $flow = new Flow(
            static function (): never {
                throw new RuntimeException('job failed');
            },
            null,
            null,
            null,
            null,
            new FiberDriver(),
        );
        $flow->fn(static function ($value) use (&$received): void {
            $received = $value;
        });
        $flow(new Ip());

        $flow->await();

        self::assertInstanceOf(FlowRuntimeException::class, $received);
        self::assertSame('job failed', $received->getMessage());
    }

    /**
     * @param DriverInterface<mixed,mixed> $driver
     */
    #[DataProvider('provideInfrastructureFailureDrainsActiveOperationsBeforePropagationCases')]
    public function testInfrastructureFailureDrainsActiveOperationsBeforePropagation(DriverInterface $driver): void
    {
        $rootDispatcher = new EventDispatcher();
        $pops = 0;
        $rootDispatcher->addListener(Event::POP, static function () use (&$pops): void {
            $pops++;
        });

        $downstreamDispatcher = new EventDispatcher();
        $downstreamDispatcher->addListener(Event::PUSH, static function ($event): void {
            if ('fail' === $event->getIp()->data) {
                throw new LogicException('asynchronous callback failed');
            }
        });
        $results = [];
        $downstream = new Flow(
            static function (string $value) use (&$results): void {
                $results[] = $value;
            },
            null,
            null,
            $downstreamDispatcher,
            null,
            $driver,
        );
        $flow = new Flow(
            static function (string $value) use ($driver): string {
                $driver->delay('slow' === $value ? 0.01 : 0.001);

                return $value;
            },
            null,
            new MaxIpStrategy(2),
            $rootDispatcher,
            null,
            $driver,
        );
        $flow->fn($downstream);
        $flow(new Ip('slow'));
        $flow(new Ip('fail'));

        try {
            $flow->await();
            self::fail('The callback exception was not propagated.');
        } catch (LogicException $exception) {
            self::assertSame('asynchronous callback failed', $exception->getMessage());
        }

        self::assertSame(2, $pops);
        self::assertSame([], $results);

        $flow(new Ip('after'));
        $flow->await();
        self::assertSame(3, $pops);
        self::assertSame(['after'], $results);
    }

    /**
     * @return iterable<string,array{DriverInterface<mixed,mixed>}>
     */
    public static function provideInfrastructureFailureDrainsActiveOperationsBeforePropagationCases(): iterable
    {
        yield 'FiberDriver' => [new FiberDriver()];
        yield 'AmpDriver' => [new AmpDriver()];
    }

    /**
     * @param DriverInterface<mixed,mixed> $driver
     */
    #[DataProvider('provideInfrastructureFailureDrainsActiveOperationsBeforePropagationCases')]
    public function testPullListenerFailureAlsoDrainsActiveOperations(DriverInterface $driver): void
    {
        $dispatcher = new EventDispatcher();
        $pulls = 0;
        $dispatcher->addListener(Event::PULL, static function () use (&$pulls): void {
            $pulls++;
            if (3 === $pulls) {
                throw new LogicException('pull listener failed');
            }
        });
        $pops = 0;
        $dispatcher->addListener(Event::POP, static function () use (&$pops): void {
            $pops++;
        });
        $flow = new Flow(
            static function (string $value) use ($driver): string {
                $driver->delay('first' === $value ? 0.01 : 0.005);

                return $value;
            },
            null,
            new MaxIpStrategy(2),
            $dispatcher,
            null,
            $driver,
        );
        $flow(new Ip('first'));
        $flow(new Ip('second'));

        try {
            $flow->await();
            self::fail('The PULL listener exception was not propagated.');
        } catch (LogicException $exception) {
            self::assertSame('pull listener failed', $exception->getMessage());
        }

        self::assertSame(2, $pops);
    }

    /**
     * @param DriverInterface<mixed,mixed> $driver
     */
    #[DataProvider('provideInfrastructureFailureDrainsActiveOperationsBeforePropagationCases')]
    public function testDriverSupervisesFailureBeforeHandlerStarts(DriverInterface $driver): void
    {
        $dispatcher = new EventDispatcher();
        $fail = true;
        $dispatcher->addListener(Event::ASYNC, static function () use (&$fail): void {
            if ($fail) {
                $fail = false;

                throw new LogicException('async listener failed');
            }
        });
        $pops = 0;
        $dispatcher->addListener(Event::POP, static function () use (&$pops): void {
            $pops++;
        });
        $results = [];
        $flow = new Flow(
            static fn (string $value): string => $value,
            null,
            new MaxIpStrategy(1),
            $dispatcher,
            null,
            $driver,
        );
        $flow->fn(static function (string $value) use (&$results): void {
            $results[] = $value;
        });
        $flow(new Ip('failed'));
        $flow(new Ip('preserved'));

        try {
            $flow->await();
            self::fail('The ASYNC listener exception was not propagated.');
        } catch (LogicException $exception) {
            self::assertSame('async listener failed', $exception->getMessage());
        }

        self::assertSame(1, $pops);
        $flow->await();
        self::assertSame(['preserved'], $results);
        self::assertSame(2, $pops);
    }

    /**
     * @param DriverInterface<mixed,mixed> $driver
     */
    #[DataProvider('provideInfrastructureFailureDrainsActiveOperationsBeforePropagationCases')]
    public function testDriverSupervisesFailureAfterHandlerStarts(DriverInterface $driver): void
    {
        $dispatcher = new EventDispatcher();
        $pops = 0;
        $dispatcher->addListener(Event::POP, static function () use (&$pops): void {
            $pops++;
        });
        $results = [];
        $flow = new Flow(
            static fn (string $value): string => $value,
            null,
            new MaxIpStrategy(1),
            $dispatcher,
            null,
            $driver,
        );
        $flow->fn(static function (string $value) use (&$results): void {
            $results[] = $value;
        });

        $fail = true;
        $dispatcher->addListener(Event::ASYNC, static function () use (&$fail): void {
            if ($fail) {
                $fail = false;

                throw new LogicException('late async listener failed');
            }
        });
        $flow(new Ip('failed'));
        $flow(new Ip('preserved'));

        try {
            $flow->await();
            self::fail('The late ASYNC listener exception was not propagated.');
        } catch (LogicException $exception) {
            self::assertSame('late async listener failed', $exception->getMessage());
        }

        self::assertSame(1, $pops);
        $flow->await();
        self::assertSame(['preserved'], $results);
        self::assertSame(2, $pops);
    }

    /**
     * @param IpStrategyInterface<mixed> $strategy
     * @param array<string>              $values
     * @param array<string>              $expected
     */
    #[DataProvider('provideSecondAwaitPreservesNonAdmittedIpsCases')]
    public function testSecondAwaitPreservesNonAdmittedIps(IpStrategyInterface $strategy, array $values, array $expected): void
    {
        $results = [];
        $flow = new Flow(
            static function (string $value): string {
                if ('bad' === $value) {
                    throw new RuntimeException('job failed');
                }

                return $value;
            },
            static function (): never {
                throw new LogicException('error job failed');
            },
            $strategy,
            null,
            null,
            new FiberDriver(),
        );
        $flow->fn(static function (string $value) use (&$results): void {
            $results[] = $value;
        });
        foreach ($values as $value) {
            $flow(new Ip($value));
        }

        try {
            $flow->await();
            self::fail('The error job exception was not propagated.');
        } catch (LogicException $exception) {
            self::assertSame('error job failed', $exception->getMessage());
        }

        $flow->await();
        self::assertSame($expected, $results);
    }

    /**
     * @return iterable<string,array{IpStrategyInterface<mixed>,array<string>,array<string>}>
     */
    public static function provideSecondAwaitPreservesNonAdmittedIpsCases(): iterable
    {
        yield 'linear' => [new LinearIpStrategy(), ['bad', 'one', 'two'], ['one', 'two']];
        yield 'stack' => [new StackIpStrategy(), ['one', 'two', 'bad'], ['two', 'one']];
        yield 'max' => [new MaxIpStrategy(1), ['bad', 'one', 'two'], ['one', 'two']];
    }

    /**
     * @param DriverInterface<T1,T2> $driver
     * @param array<mixed>           $jobs
     */
    #[DataProvider('provideJobCases')]
    public function testJob(DriverInterface $driver, array $jobs, int $resultNumber)
    {
        $count = 0;
        $flow = array_reduce(
            array_map(static function ($args) use ($driver, &$count) {
                [$job, $ipStrategy, $asyncHandler] = $args;

                return new Flow(
                    $job,
                    static function (ExceptionInterface $exception) use (&$count): void {
                        $count++;
                        self::assertSame(RuntimeException::class, $exception->getPrevious()::class);
                    },
                    $ipStrategy,
                    null,
                    $asyncHandler,
                    $driver
                );
            }, $jobs),
            static fn ($flow, $flowIt) => $flow ? $flow->fn($flowIt) : $flowIt
        );
        $flow->fn(static function (ArrayObject $data) use ($resultNumber, &$count): void {
            $count++;
            self::assertSame(ArrayObject::class, $data::class);
            self::assertSame($resultNumber, $data['number']);
        });
        $ip1 = new Ip(new ArrayObject(['number' => 0]));
        $ip2 = new Ip(new ArrayObject(['number' => 0]));
        ($flow)($ip1);
        ($flow)($ip2);

        $flow->await();

        self::assertSame(2, $count);
    }

    /**
     * @param DriverInterface<T1,T2> $driver
     * @param array<mixed>           $jobs
     */
    #[DataProvider('provideJobCases')]
    public function testTick(DriverInterface $driver, array $jobs, int $resultNumber)
    {
        $cancel = $driver->tick(1, static function () use (&$flow): void {
            $ip = new Ip(new ArrayObject(['number' => 0]));
            ($flow)($ip); // @phpstan-ignore-line
        });

        $flow = array_reduce(
            array_map(static function ($args) use ($driver, $cancel) {
                [$job, $ipStrategy, $asyncHandler] = $args;

                return new Flow(
                    $job,
                    static function (ExceptionInterface $exception) use ($cancel): void {
                        self::assertSame(RuntimeException::class, $exception->getPrevious()::class);
                        $cancel();
                    },
                    $ipStrategy,
                    null,
                    $asyncHandler,
                    $driver
                );
            }, $jobs),
            static fn ($flow, $flowIt) => $flow ? $flow->fn($flowIt) : $flowIt
        );
        $flow->fn(static function (ArrayObject $data) use ($resultNumber) {
            self::assertSame(ArrayObject::class, $data::class);
            self::assertSame($resultNumber, $data['number']);

            return $data;
        });

        $flow->fn(static function () use ($cancel): void {
            $cancel();
        });

        $flow->await();
    }

    /**
     * @return array<array<mixed>>
     */
    public static function provideJobCases(): iterable
    {
        $exception = new RuntimeException('job error');

        return self::matrix(static function (DriverInterface $driver, $strategyBuilder) use ($exception) {
            $cases = [];

            $cases['closureJob'] = [[[static function (ArrayObject $data) {
                $data['number'] = 5;

                return $data;
            }, $strategyBuilder(), new AsyncHandler()]], 5];

            $cases['classJob'] = [[[new ClosureJob(static function (ArrayObject $data) {
                $data['number'] = 6;

                return $data;
            }), $strategyBuilder(), new AsyncHandler()]], 6];

            $strategy = $strategyBuilder();
            if (!$driver instanceof FiberDriver && !$strategy instanceof MaxIpStrategy) {
                $cases['asyncJob'] = [[[static function (ArrayObject $data) use ($driver) {
                    $driver->delay(1 / 1000);
                    $data['number'] = 5;

                    return $data;
                }, $strategy, new AsyncHandler()]], 5];
            }

            $cases['exceptionJob'] = [[[static function () use ($exception): void {
                throw $exception;
            }, $strategyBuilder(), new AsyncHandler()]], 0];

            if ($driver instanceof AmpDriver || $driver instanceof ReactDriver) {
                $cases['deferJob'] = [[[static function ($args) {
                    [$data, $defer] = $args;

                    return $defer(static function ($complete) use ($data, $defer): void {
                        $data['number'] = 8;
                        $complete([$data, $defer]);
                    });
                }, $strategyBuilder(), new DeferAsyncHandler()]], 8];
            }

            $strategy = $strategyBuilder();
            if (!$strategy instanceof MaxIpStrategy) {
                $cases['batchJob'] = [[[static function (ArrayObject $data) {
                    $data['number'] = 6;

                    return $data;
                }, $strategy, new BatchAsyncHandler(2)]], 6];
            }

            return $cases;
        });
    }

    /**
     * @param DriverInterface<T1,T2> $driver
     * @param array<mixed>           $config
     */
    #[DataProvider('provideDoCases')]
    public function testDo(DriverInterface $driver, callable $callable, ?array $config, int $resultNumber)
    {
        $ip = new Ip(new ArrayObject(['number' => 0]));
        $flow = (new FlowFactory())->create($callable, [
            ...['driver' => $driver],
            ...($config ?? []),
        ])->fn(static function (ArrayObject $data) use ($resultNumber) {
            self::assertSame(ArrayObject::class, $data::class);
            self::assertSame($resultNumber, $data['number']);

            return $data;
        });

        ($flow)($ip);

        $flow->await();
    }

    /**
     * @return array<array<mixed>>
     */
    public static function provideDoCases(): iterable
    {
        return self::matrix(static fn (DriverInterface $driver, $strategyBuilder) => [
            'simpleGenerator' => [static function () use ($driver, $strategyBuilder) {
                yield [static function (ArrayObject $data) {
                    $data['number'] = 5;

                    return $data;
                }, null, $strategyBuilder()];
                yield [static function (ArrayObject $data) use ($driver) {
                    $driver->delay(1 / 1000);
                    $data['number'] = 10;

                    return $data;
                }, null, $strategyBuilder()];
            }, null, 10],
        ]);
    }
}
