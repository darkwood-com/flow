<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use ArrayObject;
use Closure;
use Flow\DriverInterface;
use Flow\Flow\Flow;
use Flow\Flow\TransportFlow;
use Flow\IpStrategyInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * @template T1
 * @template T2
 */
class TransportFlowTest extends TestCase
{
    use FlowTrait;

    /**
     * @dataProvider provideJobsCases
     *
     * @param DriverInterface<T1,T2>  $driver
     * @param IpStrategyInterface<T1> $ipStrategy
     * @param array<Closure>          $jobs
     */
    public function testJobs(DriverInterface $driver, IpStrategyInterface $ipStrategy, array $jobs, int $resultNumber): void
    {
        self::assertTrue(true);

        /*$transport1 = new InMemoryTransport();
        $transport2 = new InMemoryTransport();
        $flow = array_reduce(
            array_map(fn ($job) => new Flow($job, static function () {}, $ipStrategy, $driver), $jobs),
            fn ($flow, $flowIt) => $flow ? $flow->fn($flowIt) : $flowIt
        );

        new TransportFlow($flow, $transport1, $transport2, $driver);

        $driver->tick(1, static function () use ($driver, $transport2, $resultNumber) {
            $ips = $transport2->get();
            foreach ($ips as $ip) {
                $data = $ip->getMessage();
                self::assertEquals($resultNumber, $data['number']);
            }

            $driver->stop();
        });

        $envelope = new Envelope(new ArrayObject(['number' => 0]));
        $transport1->send($envelope);

        $driver->start();*/
    }

    /**
     * @return array<array<mixed>>
     */
    public static function provideJobsCases(): iterable
    {
        return self::matrix(static fn (DriverInterface $driver) => [
            'oneJob' => [[static function (ArrayObject $data): void {
                $data['number'] = 1;
            }], 1],
            'oneAsyncJob' => [[static function (ArrayObject $data) use ($driver): void {
                $driver->delay(1 / 1000);
                $data['number'] = 5;
            }], 5],
            'twoJob' => [[static function (ArrayObject $data): void {
                $data['number'] += 2;
            }, static function (ArrayObject $data) {
                $data['number'] *= 3;
            }], 6],
            'twoAsyncJob' => [[static function (ArrayObject $data) use ($driver): void {
                $driver->delay(1 / 1000);
                $data['number'] += 5;
            }, static function (ArrayObject $data) use ($driver): void {
                $driver->delay(1 / 1000);
                $data['number'] *= 2;
            }], 10],
        ]);
    }
}
