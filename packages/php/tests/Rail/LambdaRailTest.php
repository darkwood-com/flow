<?php

declare(strict_types=1);

namespace RFBP\Test\Rail;

use ArrayObject;
use Closure;
use RFBP\DriverInterface;
use RFBP\Ip;
use RFBP\IpStrategyInterface;
use RFBP\Rail\LambdaRail;
use Throwable;

class LambdaRailTest extends AbstractRailTest
{
    /**
     * @dataProvider jobProvider
     */
    public function testJob(DriverInterface $driver, IpStrategyInterface $ipStrategy, Closure $job, int $resultNumber, ?Throwable $resultException): void
    {
        $ip = new Ip(new ArrayObject(['number' => 6]));
        $lambdaRail = new LambdaRail($job, $ipStrategy, $driver);
        $lambdaRail->pipe(function (Ip $ip, ?Throwable $exception) use ($driver, $resultNumber, $resultException) {
            $driver->stop();
            self::assertSame(ArrayObject::class, $ip->getData()::class);
            self::assertSame($resultNumber, $ip->getData()['number']);
            self::assertSame($resultException, $exception);
        });
        ($lambdaRail)($ip);

        $driver->run();
    }

    /**
     * @return array<array>
     */
    public function jobProvider(): array
    {
        return $this->matrix([
            'job' => [static function (callable $function): Closure {
                return static function (ArrayObject $data) use ($function) {
                    if($data['number'] > 1) {
                        $calcData = new ArrayObject(['number' => $data['number'] - 1]);
                        $function($calcData);
                        $data['number'] = $data['number'] * $calcData['number'];
                    } else {
                        $data['number'] = 1;
                    }
                };
            }, 720, null],
        ]);
    }
}
