<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use ArrayObject;
use Closure;
use Flow\DriverInterface;
use Flow\Flow\Flow;
use Flow\Ip;
use Flow\IpStrategyInterface;
use RuntimeException;

class FlowTest extends AbstractFlowTest
{
    /**
     * @dataProvider jobProvider
     *
     * @param array<Closure> $jobs
     */
    public function testJob(DriverInterface $driver, IpStrategyInterface $ipStrategy, array $jobs, int $resultNumber): void
    {
        $ip = new Ip(new ArrayObject(['number' => 0]));
        $flow = array_reduce(
            array_map(fn ($job) => new Flow($job, static function () {}, $ipStrategy, $driver), $jobs),
            fn ($flow, $flowIt) => $flow ? $flow->fn($flowIt) : $flowIt
        );
        ($flow)($ip, function (Ip $ip) use ($driver, $resultNumber) {
            $driver->stop();
            self::assertSame(ArrayObject::class, $ip->data::class);
            self::assertSame($resultNumber, $ip->data['number']);
        });

        $driver->start();
    }

    /**
     * @dataProvider jobProvider
     */
    public function testJobs(DriverInterface $driver): void
    {
        $ip1 = new Ip(new ArrayObject(['n1' => 3, 'n2' => 4]));
        $ip2 = new Ip(new ArrayObject(['n1' => 2, 'n2' => 5]));

        $jobs = [function (object $data): void {
            $data['n1'] *= 2;
        }, function (object $data): void {
            $data['n2'] *= 4;
        }];
        $errorJobs = [function () {
        }, function () {
        }];
        $flow = new Flow($jobs, $errorJobs, null, $driver);

        $ips = [];

        $callback = function (Ip $ip) use ($driver, &$ips, $ip1, $ip2) {
            $ips[] = $ip;
            if (count($ips) === 2) {
                $this->assertSame($ip1, $ips[0]);
                $this->assertSame($ip2, $ips[1]);
                self::assertSame(6, $ip1->data['n1']);
                self::assertSame(16, $ip1->data['n2']);
                self::assertSame(4, $ip2->data['n1']);
                self::assertSame(20, $ip2->data['n2']);

                $driver->stop();
            }
        };

        ($flow)($ip1, $callback);
        ($flow)($ip2, $callback);

        $driver->start();
    }

    /**
     * @return array<array<mixed>>
     */
    public function jobProvider(): array
    {
        $exception = new RuntimeException('job error');

        return $this->matrix(fn (DriverInterface $driver) => [
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
            'exceptionJob' => [[static function () use ($exception) {
                throw $exception;
            }], 0],
        ]);
    }
}
