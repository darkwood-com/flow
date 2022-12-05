<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use ArrayObject;
use Closure;
use Flow\DriverInterface;
use Flow\Ip;
use Flow\IpStrategyInterface;
use Flow\Flow\LambdaFlow;
use Throwable;

class LambdaFlowTest extends AbstractFlowTest
{
    /**
     * @dataProvider jobProvider
     */
    public function testJob(DriverInterface $driver, IpStrategyInterface $ipStrategy, Closure $job, int $resultNumber, ?Throwable $resultException): void
    {
        $ip = new Ip(new ArrayObject(['number' => 6]));
        $lambdaFlow = new LambdaFlow($job, $ipStrategy, $driver);
        $lambdaFlow->pipe(function (Ip $ip, ?Throwable $exception) use ($driver, $resultNumber, $resultException) {
            $driver->stop();
            self::assertSame(ArrayObject::class, $ip->getData()::class);
            self::assertSame($resultNumber, $ip->getData()['number']);
            self::assertSame($resultException, $exception);
        });
        ($lambdaFlow)($ip);

        $driver->run();
    }

    /**
     * @return array<array<mixed>>
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
