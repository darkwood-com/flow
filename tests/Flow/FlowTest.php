<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use ArrayObject;
use Flow\Driver\AmpDriver;
use Flow\DriverInterface;
use Flow\ExceptionInterface;
use Flow\Flow\Flow;
use Flow\Ip;
use Flow\IpStrategyInterface;
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
     * @param DriverInterface<T1,T2>  $driver
     * @param IpStrategyInterface<T1> $ipStrategy
     * @param array<mixed>            $jobs
     */
    public function testJob(DriverInterface $driver, IpStrategyInterface $ipStrategy, array $jobs, int $resultNumber): void
    {
        $flow = array_reduce(
            array_map(static fn ($job) => new Flow(
                $job,
                static function (ExceptionInterface $exception) {
                    self::assertSame(RuntimeException::class, $exception->getPrevious()::class);
                },
                $ipStrategy,
                null,
                $driver
            ), $jobs),
            static fn ($flow, $flowIt) => $flow ? $flow->fn($flowIt) : $flowIt
        );
        $flow->fn(static function (ArrayObject $data) use ($resultNumber) {
            self::assertSame(ArrayObject::class, $data::class);
            self::assertSame($resultNumber, $data['number']);
        });
        $ip = new Ip(new ArrayObject(['number' => 0]));
        ($flow)($ip);

        $flow->await();
    }

    /**
     * @dataProvider provideJobCases
     *
     * @param DriverInterface<T1,T2>  $driver
     * @param IpStrategyInterface<T1> $ipStrategy
     * @param array<mixed>            $jobs
     */
    public function testTick(DriverInterface $driver, IpStrategyInterface $ipStrategy, array $jobs, int $resultNumber): void
    {
        $cancel = $driver->tick(1, static function () use (&$flow) {
            $ip = new Ip(new ArrayObject(['number' => 0]));
            ($flow)($ip); // @phpstan-ignore-line
        });

        $flow = array_reduce(
            array_map(static fn ($job) => new Flow(
                $job,
                static function (ExceptionInterface $exception) use ($cancel) {
                    self::assertSame(RuntimeException::class, $exception->getPrevious()::class);
                    $cancel();
                },
                $ipStrategy,
                null,
                $driver
            ), $jobs),
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
     * @param DriverInterface<T1,T2>  $driver
     * @param IpStrategyInterface<T1> $ipStrategy
     * @param array<mixed>            $config
     */
    public function testDo(DriverInterface $driver, IpStrategyInterface $ipStrategy, callable $callable, ?array $config, int $resultNumber): void
    {
        $ip = new Ip(new ArrayObject(['number' => 0]));
        $flow = Flow::do($callable, [
            ...['driver' => $driver, 'ipStrategy' => $ipStrategy],
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

        return self::matrix(static fn (DriverInterface $driver) => [
            'job' => [[static function (ArrayObject $data) {
                $data['number'] = 5;

                return $data;
            }], 5],
            'asyncJob' => [[static function (ArrayObject $data) use ($driver) {
                $driver->delay(1 / 1000);
                $data['number'] = 5;

                return $data;
            }], 5],
            'exceptionJob' => [[static function () use ($exception) {
                throw $exception;
            }], 0],
        ]);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function provideDoCases(): iterable
    {
        return self::matrix(static fn (DriverInterface $driver) => [
            'simpleGenerator' => [static function () use ($driver) {
                if ($driver::class !== AmpDriver::class) {
                    yield static function (ArrayObject $data) {
                        $data['number'] = 5;

                        return $data;
                    };
                }
                yield static function (ArrayObject $data) use ($driver) {
                    $driver->delay(1 / 1000);
                    $data['number'] = 10;

                    return $data;
                };
            }, null, 10],
        ]);
    }
}
