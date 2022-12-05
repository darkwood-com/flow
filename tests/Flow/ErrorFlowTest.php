<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use ArrayObject;
use Closure;
use Flow\DriverInterface;
use Flow\Ip;
use Flow\IpStrategyInterface;
use Flow\Flow\ErrorFlow;
use Flow\Flow\Flow;
use RuntimeException;
use Throwable;

class ErrorFlowTest extends AbstractFlowTest
{
    /**
     * @dataProvider jobProvider
     */
    public function testJob(DriverInterface $driver, IpStrategyInterface $ipStrategy, Closure $job, int $resultNumber, ?Throwable $resultException): void
    {
        $ip = new Ip(new ArrayObject(['number' => 0]));
        $flow = new Flow(function () {
            throw new RuntimeException('job flow error');
        });
        $errorFlow = new ErrorFlow($flow, $job, $ipStrategy, $driver);
        $errorFlow->pipe(function (Ip $ip, ?Throwable $exception) use ($driver, $resultNumber, $resultException) {
            $driver->stop();
            self::assertSame(ArrayObject::class, $ip->getData()::class);
            self::assertSame($resultNumber, $ip->getData()['number']);
            self::assertSame($resultException, $exception);
        });
        ($errorFlow)($ip);

        $driver->run();
    }

    /**
     * @return array<array<mixed>>
     */
    public function jobProvider(): array
    {
        return $this->matrix([
            'job' => [static function (ArrayObject $data) {
                $data['number'] = 5;
            }, 5, null],
        ]);
    }
}
