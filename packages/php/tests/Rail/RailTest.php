<?php

declare(strict_types=1);

namespace RFBP\Test\Rail;

use ArrayObject;
use Closure;
use RFBP\DriverInterface;
use RFBP\Ip;
use RFBP\IpStrategyInterface;
use RFBP\Rail\Rail;
use RuntimeException;
use Throwable;

class RailTest extends AbstractRailTest
{
    /**
     * @dataProvider jobProvider
     */
    public function testJob(DriverInterface $driver, IpStrategyInterface $ipStrategy, Closure $job, int $resultNumber, ?Throwable $resultException): void
    {
        $ip = new Ip(new ArrayObject(['number' => 0]));
        $rail = new Rail($job, $ipStrategy, $driver);
        $rail->pipe(function (Ip $ip, ?Throwable $exception) use ($driver, $resultNumber, $resultException) {
            $driver->stop();
            self::assertSame(ArrayObject::class, $ip->getData()::class);
            self::assertSame($resultNumber, $ip->getData()['number']);
            self::assertSame($resultException, $exception);
        });
        ($rail)($ip);

        $driver->run();
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
        $rail = new Rail($jobs, null, $driver);

        $ips = [];
        $rail->pipe(function (Ip $ip) use ($driver, &$ips, $ip1, $ip2) {
            $ips[] = $ip;
            if(count($ips) === 2) {
                $this->assertSame($ip1, $ips[0]);
                $this->assertSame($ip2, $ips[1]);
                self::assertSame(6, $ip1->getData()['n1']);
                self::assertSame(16, $ip1->getData()['n2']);
                self::assertSame(4, $ip2->getData()['n1']);
                self::assertSame(20, $ip2->getData()['n2']);

                $driver->stop();
            }
        });

        ($rail)($ip1);
        ($rail)($ip2);

        $driver->run();
    }

    /**
     * @return array<array>
     */
    public function jobProvider(): array
    {
        $exception = new RuntimeException('job error');

        return $this->matrix([
            'job' => [static function (ArrayObject $data) {
                $data['number'] = 5;
            }, 5, null],
            'exceptionJob' => [static function () use ($exception) {
                throw $exception;
            }, 0, $exception],
        ]);
    }
}
