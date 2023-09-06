<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use ArrayObject;
use Closure;
use Flow\DriverInterface;
use Flow\Flow\YFlow;
use Flow\Ip;
use Flow\IpStrategyInterface;
use PHPUnit\Framework\TestCase;

class YFlowTest extends TestCase
{
    use FlowTrait;

    /**
     * @dataProvider provideJobCases
     */
    public function testJob(DriverInterface $driver, IpStrategyInterface $ipStrategy, Closure $job, int $resultNumber): void
    {
        $ip = new Ip(new ArrayObject(['number' => 6]));
        $errorJob = static function () {};
        $yFlow = new YFlow($job, $errorJob, $ipStrategy, $driver);
        ($yFlow)($ip, static function (Ip $ip) use ($resultNumber) {
            self::assertSame(ArrayObject::class, $ip->data::class);
            self::assertSame($resultNumber, $ip->data['number']);
        });
    }

    /**
     * @return array<array<mixed>>
     */
    public static function provideJobCases(): iterable
    {
        return self::matrix(static fn () => [
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
