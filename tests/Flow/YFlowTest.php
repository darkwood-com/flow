<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use ArrayObject;
use Closure;
use Flow\DriverInterface;
use Flow\Flow\YFlow;
use Flow\Ip;
use Flow\IpStrategyInterface;

class YFlowTest extends AbstractFlowTest
{
    /**
     * @dataProvider jobProvider
     */
    public function testJob(DriverInterface $driver, IpStrategyInterface $ipStrategy, Closure $job, int $resultNumber): void
    {
        $ip = new Ip(new ArrayObject(['number' => 6]));
        $errorJob = static function () {};
        $yFlow = new YFlow($job, $errorJob, $ipStrategy, $driver);
        ($yFlow)($ip, function (Ip $ip) use ($resultNumber) {
            self::assertSame(ArrayObject::class, $ip->data::class);
            self::assertSame($resultNumber, $ip->data['number']);
        });
    }

    /**
     * @return array<array<mixed>>
     */
    public function jobProvider(): array
    {
        return $this->matrix(fn () => [
            'job' => [static function (callable $function): Closure {
                return static function (ArrayObject $data) use ($function) {
                    if ($data['number'] > 1) {
                        $calcData = new ArrayObject(['number' => $data['number'] - 1]);
                        $function($calcData);
                        $data['number'] = $data['number'] * $calcData['number'];
                    } else {
                        $data['number'] = 1;
                    }
                };
            }, 720],
        ]);
    }
}
