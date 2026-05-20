<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use ArrayObject;
use Closure;
use Flow\DriverInterface;
use Flow\Flow\YFlow;
use Flow\Ip;
use Flow\IpStrategyInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @template T1
 * @template T2
 */
class YFlowTest extends TestCase
{
    use FlowTrait;

    /**
     * @param DriverInterface<T1,T2>  $driver
     * @param IpStrategyInterface<T1> $ipStrategy
     */
    #[DataProvider('provideJobCases')]
    public function testJob(DriverInterface $driver, Closure $job, IpStrategyInterface $ipStrategy, int $resultNumber): void
    {
        $ip = new Ip(new ArrayObject(['number' => 6]));
        $errorJob = static function (): void {};
        $yFlow = (new YFlow($job, $errorJob, $ipStrategy, null, null, $driver))
            ->fn(static function (ArrayObject $data) use ($resultNumber): void {
                self::assertSame($resultNumber, $data['number']); // @phpstan-ignore offsetAccess.notFound
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
        return self::matrix(static fn (DriverInterface $driver, $strategyBuilder) => [
            'job' => [static function (callable $factorial): Closure {
                return static function (ArrayObject $data) use ($factorial) {
                    return new ArrayObject([
                        'number' => ($data['number'] <= 1) ? 1 : $data['number'] * $factorial(new ArrayObject(['number' => $data['number'] - 1]))['number'],
                    ]);
                };
            }, $strategyBuilder(), 720],
        ]);
    }
}
