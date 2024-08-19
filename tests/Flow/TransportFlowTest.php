<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use ArrayObject;
use Flow\DriverInterface;
use Flow\Flow\Flow;
use Flow\Flow\TransportFlow;
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
     * @param DriverInterface<T1,T2> $driver
     * @param array<mixed>           $jobs
     */
    public function testJobs(DriverInterface $driver, array $jobs, int $resultNumber): void
    {
        $flow = array_reduce(
            array_map(static function ($args) use ($driver) {
                [$job, $ipStrategy] = $args;

                return new Flow($job, static function () {}, $ipStrategy, null, null, $driver);
            }, $jobs),
            static fn ($flow, $flowIt) => $flow ? $flow->fn($flowIt) : $flowIt
        );
        $flow->fn(static function (ArrayObject $data) use ($resultNumber) {
            self::assertSame(ArrayObject::class, $data::class);
            self::assertSame($resultNumber, $data['number']);

            return $data;
        });

        $producerTransport = new InMemoryTransport();
        $consumerTransport = new InMemoryTransport();
        $transportFlow = new TransportFlow($flow, $producerTransport, $consumerTransport, $driver);
        $cancel = $transportFlow->pull(1);

        $envelope = new Envelope(new ArrayObject(['number' => 0]));
        $producerTransport->send($envelope);

        $flow->fn(static function (ArrayObject $data) use ($cancel) {
            $cancel();

            return $data;
        });

        $flow->await();
    }

    /**
     * @return array<array<mixed>>
     */
    public static function provideJobsCases(): iterable
    {
        return self::matrix(static fn (DriverInterface $driver, $strategyBuilder) => [
            'job' => [[[static function (ArrayObject $data) {
                $data['number'] = 1;

                return $data;
            }, $strategyBuilder()]], 1],
            'asyncJob' => [[[static function (ArrayObject $data) use ($driver) {
                $driver->delay(1 / 1000);
                $data['number'] = 5;

                return $data;
            }, $strategyBuilder()]], 5],
        ]);
    }
}
