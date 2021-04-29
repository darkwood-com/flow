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
