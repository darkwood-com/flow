<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use ArrayObject;
use Closure;
use Flow\DriverInterface;
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
     * @dataProvider jobProvider
     *
     * @param DriverInterface<T1,T2>  $driver
     * @param IpStrategyInterface<T1> $ipStrategy
     * @param array<Closure>          $jobs
     */
    public function testJob(DriverInterface $driver, IpStrategyInterface $ipStrategy, array $jobs, int $resultNumber): void
    {
        $ip = new Ip(new ArrayObject(['number' => 0]));
        $flow = array_reduce(
            array_map(static fn ($job) => new Flow($job, static function () {}, $ipStrategy, $driver), $jobs),
            static fn ($flow, $flowIt) => $flow ? $flow->fn($flowIt) : $flowIt
        );
        ($flow)($ip, static function (Ip $ip) use ($resultNumber) {
            self::assertSame(ArrayObject::class, $ip->data::class);
            self::assertSame($resultNumber, $ip->data['number']);
        });
    }

    /**
     * @dataProvider jobProvider
     *
     * @param DriverInterface<T1,T2|void> $driver
     */
    public function testJobs(DriverInterface $driver): void
    {
        $ip1 = new Ip(new ArrayObject(['n1' => 3, 'n2' => 4]));
        $ip2 = new Ip(new ArrayObject(['n1' => 2, 'n2' => 5]));

        $jobs = [static function (object $data): void {
            $data['n1'] *= 2;
        }, static function (object $data): void {
            $data['n2'] *= 4;
        }];
        $errorJobs = [static function () {
        }, static function () {
        }];
        $flow = new Flow($jobs, $errorJobs, null, $driver);

        $ips = new ArrayObject();

        $callback = function (Ip $ip) use ($ips, $ip1, $ip2) {
            $ips->append($ip);
            if ($ips->count() === 2) {
                $this->assertSame($ip1, $ips->offsetGet(0));
                $this->assertSame($ip2, $ips->offsetGet(1));
                self::assertSame(6, $ip1->data['n1']);
                self::assertSame(16, $ip1->data['n2']);
                self::assertSame(4, $ip2->data['n1']);
                self::assertSame(20, $ip2->data['n2']);
            }
        };

        ($flow)($ip1, $callback);
        ($flow)($ip2, $callback);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function jobProvider(): iterable
    {
        $exception = new RuntimeException('job error');

        return self::matrix(static fn (DriverInterface $driver) => [
            'oneJob' => [[static function (ArrayObject $data) {
                $data['number'] = 5;
            }], 5],
            'oneAsyncJob' => [[static function (ArrayObject $data) use ($driver): void {
                $driver->delay(1 / 1000);
                $data['number'] = 5;
            }], 5],
            'twoAsyncJob' => [[static function (ArrayObject $data) use ($driver): void {
                $driver->delay(1 / 1000);
                $data['number'] += 5;
            }, static function (ArrayObject $data) use ($driver): void {
                $driver->delay(1 / 1000);
                $data['number'] *= 2;
            }], 10],
            /*'exceptionJob' => [[static function () use ($exception) {
                throw $exception;
            }], 0],*/
        ]);
    }
}
