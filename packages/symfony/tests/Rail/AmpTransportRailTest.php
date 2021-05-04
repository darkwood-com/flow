<?php

declare(strict_types=1);

namespace RFBP\Test;

use function Amp\delay;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use ArrayObject;
use Closure;
use RFBP\Driver\AmpDriver;
use RFBP\DriverInterface;
use RFBP\Rail\Rail;
use RFBP\Rail\SequenceRail;
use RFBP\Rail\TransportRail;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class AmpTransportRailTest extends AsyncTestCase
{
    /**
     * @dataProvider jobsProvider
     *
     * @param array<Closure> $jobs
     */
    public function testIpWithoutId(array $jobs, DriverInterface $driver): void
    {
        $transport1 = new InMemoryTransport();
        $transport2 = new InMemoryTransport();
        $rail = new SequenceRail(array_map(static function ($job) use ($driver) { return new Rail($job, null, $driver); }, $jobs));

        new TransportRail($rail, $transport1, $transport2);
        $envelope = new Envelope(new stdClass());
        $transport1->send($envelope);
        $this->expectException(RuntimeException::class);
        $driver->run();
    }

    /**
     * @dataProvider jobsProvider
     *
     * @param array<Closure> $jobs
     */
    public function testJobs(array $jobs, DriverInterface $driver, int $resultNumber): void
    {
        $transport1 = new InMemoryTransport();
        $transport2 = new InMemoryTransport();
        $rail = new SequenceRail(array_map(static function ($job) use ($driver) { return new Rail($job, null, $driver); }, $jobs));

        new TransportRail($rail, $transport1, $transport2);

        Loop::repeat(1, static function () use ($driver, $transport2, $resultNumber) {
            $ips = $transport2->get();
            foreach ($ips as $ip) {
                $data = $ip->getMessage();
                self::assertEquals($resultNumber, $data['number']);

                $driver->stop();
            }
        });

        $envelope = Envelope::wrap(new ArrayObject(['number' => 0]), [new TransportMessageIdStamp('envelope_id')]);
        $transport1->send($envelope);

        $driver->run();
    }

    /**
     * @return array<array>
     */
    public function jobsProvider(): array
    {
        $driver = new AmpDriver();

        return [
            'oneJob' => [[static function (ArrayObject $data) {
                $data['number'] = 1;
            }], $driver, 1],
            'oneAsyncJob' => [[static function (ArrayObject $data) {
                yield delay(10);
                $data['number'] = 5;
            }], $driver, 5],
            'twoJob' => [[static function (ArrayObject $data) {
                $data['number'] += 2;
            }, static function (ArrayObject $data) {
                $data['number'] *= 3;
            }], $driver, 6],
            'twoAsyncJob' => [[static function (ArrayObject $data) {
                yield delay(10);
                $data['number'] += 5;
            }, static function (ArrayObject $data) {
                yield delay(10);
                $data['number'] *= 2;
            }], $driver, 10],
        ];
    }
}
