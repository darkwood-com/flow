<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use ArrayObject;
use Flow\AsyncHandler\AsyncHandler;
use Flow\AsyncHandler\BatchAsyncHandler;
use Flow\AsyncHandler\DeferAsyncHandler;
use Flow\Driver\AmpDriver;
use Flow\Driver\ReactDriver;
use Flow\DriverInterface;
use Flow\ExceptionInterface;
use Flow\Flow\Flow;
use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @template T1
 * @template T2
 */
class FlowTest extends TestCase
{
    use FlowTrait;

    /**
     * @dataProvider provideJobCases
     *
     * @param DriverInterface<T1,T2> $driver
     * @param array<mixed>           $jobs
     */
    public function testJob(DriverInterface $driver, array $jobs, int $resultNumber): void
    {
        $count = 0;
        $flow = array_reduce(
            array_map(static function ($args) use ($driver, &$count) {
                [$job, $ipStrategy, $asyncHandler] = $args;

                return new Flow(
                    $job,
                    static function (ExceptionInterface $exception) use (&$count) {
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
        $flow->fn(static function (ArrayObject $data) use ($resultNumber, &$count) {
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
     * @dataProvider provideJobCases
     *
     * @param DriverInterface<T1,T2> $driver
     * @param array<mixed>           $jobs
     */
    public function testTick(DriverInterface $driver, array $jobs, int $resultNumber): void
    {
        $cancel = $driver->tick(1, static function () use (&$flow) {
            $ip = new Ip(new ArrayObject(['number' => 0]));
            ($flow)($ip); // @phpstan-ignore-line
        });

        $flow = array_reduce(
            array_map(static function ($args) use ($driver, $cancel) {
                [$job, $ipStrategy, $asyncHandler] = $args;

                return new Flow(
                    $job,
                    static function (ExceptionInterface $exception) use ($cancel) {
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

        $flow->fn(static function () use ($cancel) {
            $cancel();
        });

        $flow->await();
    }

    /**
     * @dataProvider provideDoCases
     *
     * @param DriverInterface<T1,T2> $driver
     * @param array<mixed>           $config
     */
    public function testDo(DriverInterface $driver, callable $callable, ?array $config, int $resultNumber): void
    {
        $ip = new Ip(new ArrayObject(['number' => 0]));
        $flow = Flow::do($callable, [
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
    public static function provideJobCases(): iterable
    {
        $exception = new RuntimeException('job error');

        return self::matrix(static function (DriverInterface $driver, $strategyBuilder) use ($exception) {
            $cases = [
                'job' => [[[static function (ArrayObject $data) {
                    $data['number'] = 5;

                    return $data;
                }, $strategyBuilder(), new AsyncHandler()]], 5],
                'asyncJob' => [[[static function (ArrayObject $data) use ($driver) {
                    $driver->delay(1 / 1000);
                    $data['number'] = 5;

                    return $data;
                }, $strategyBuilder(), new AsyncHandler()]], 5],
                'exceptionJob' => [[[static function () use ($exception) {
                    throw $exception;
                }, $strategyBuilder(), new AsyncHandler()]], 0],
            ];

            if ($driver instanceof AmpDriver || $driver instanceof ReactDriver) {
                $cases['deferJob'] = [[[static function ($args) {
                    [$data, $defer] = $args;

                    return $defer(static function ($complete) use ($data, $defer) {
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
