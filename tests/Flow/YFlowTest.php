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

/**
 * @template T1
 * @template T2
 */
class YFlowTest extends TestCase
{
    use FlowTrait;

    /**
     * @dataProvider provideJobCases
     *
     * @param DriverInterface<T1,T2>  $driver
     * @param IpStrategyInterface<T1> $ipStrategy
     */
    public function testJob(DriverInterface $driver, IpStrategyInterface $ipStrategy, Closure $job, int $resultNumber): void
    {
        $ip = new Ip(new ArrayObject(['number' => 6]));
        $errorJob = static function () {};
        $yFlow = (new YFlow($job, $errorJob, $ipStrategy, null, null, $driver))
            ->fn(static function (ArrayObject $data) use ($resultNumber) {
                self::assertSame($resultNumber, $data['number']);
            })
        ;
        $yFlow($ip);

        $yFlow->await();
    }

    /**
     * @return array<array<mixed>>
     */
    public static function provideJobCases(): iterable
    {
        return self::matrix(static fn () => [
            'job' => [static function (callable $factorial): Closure {
                return static function (ArrayObject $data) use ($factorial) {
                    return new ArrayObject([
                        'number' => ($data['number'] <= 1) ? 1 : $data['number'] * $factorial(new ArrayObject(['number' => $data['number'] - 1]))['number'],
                    ]);
                };
            }, 720],
        ]);
    }
}
