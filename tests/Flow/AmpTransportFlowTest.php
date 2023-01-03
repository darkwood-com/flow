<?php

declare(strict_types=1);

namespace Flow\Test;

use function Amp\delay;

use ArrayObject;
use Closure;
use Flow\Driver\AmpDriver;
use Flow\DriverInterface;
use Flow\Flow\Flow;
use Flow\Flow\TransportFlow;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class AmpTransportFlowTest extends TestCase
{
    /**
     * @dataProvider jobsProvider
     *
     * @param array<Closure> $jobs
     */
    public function testJobs(array $jobs, DriverInterface $driver, int $resultNumber): void
    {
        $transport1 = new InMemoryTransport();
        $transport2 = new InMemoryTransport();
        $flow = array_reduce($jobs, static function ($flow, $job) use ($driver) {
            $jobFlow = new Flow($job, static function () {}, null, $driver);
            if ($flow === null) {
                $flow = $jobFlow;
            } else {
                $flow->fn($jobFlow);
            }

            return $flow;
        }, null);

        new TransportFlow($flow, $transport1, $transport2, $driver);

        $driver->tick(1, static function () use ($driver, $transport2, $resultNumber) {
            $ips = $transport2->get();
            foreach ($ips as $ip) {
                $data = $ip->getMessage();
                self::assertEquals($resultNumber, $data['number']);

                $driver->stop();
            }
        });

        $envelope = new Envelope(new ArrayObject(['number' => 0]));
        $transport1->send($envelope);

        $driver->start();
    }

    /**
     * @return array<array<mixed>>
     */
    public function jobsProvider(): array
    {
        $driver = new AmpDriver();

        return [
            'oneJob' => [[static function (ArrayObject $data): void {
                $data['number'] = 1;
            }], $driver, 1],
            'oneAsyncJob' => [[static function (ArrayObject $data): void {
                delay(1 / 1000);
                $data['number'] = 5;
            }], $driver, 5],
            'twoJob' => [[static function (ArrayObject $data): void {
                $data['number'] += 2;
            }, static function (ArrayObject $data) {
                $data['number'] *= 3;
            }], $driver, 6],
            'twoAsyncJob' => [[static function (ArrayObject $data): void {
                delay(1 / 1000);
                $data['number'] += 5;
            }, static function (ArrayObject $data): void {
                delay(1 / 1000);
                $data['number'] *= 2;
            }], $driver, 10],
        ];
    }
}
