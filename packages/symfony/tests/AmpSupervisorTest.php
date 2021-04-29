<?php

declare(strict_types=1);

namespace RFBP\Test;

use function Amp\delay;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use ArrayObject;
use Closure;
use RFBP\Rail\Rail;
use RFBP\Supervisor;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class AmpSupervisorTest extends AsyncTestCase
{
    /**
     * @dataProvider jobsProvider
     *
     * @param array<Closure> $jobs
     */
    public function testIpWithoutId(array $jobs): void
    {
        $transport1 = new InMemoryTransport();
        $transport2 = new InMemoryTransport();
        $rails = array_map(static function ($job) { return new Rail($job); }, $jobs);

        $supervisor = new Supervisor($rails, null, $transport1, $transport2);
        $envelope = new Envelope(new stdClass());
        $transport1->send($envelope);
        $this->expectException(RuntimeException::class);
        $supervisor->run();
    }

    /**
     * @dataProvider jobsProvider
     *
     * @param array<Closure> $jobs
     */
    public function testJobs(array $jobs, int $resultNumber): void
    {
        $transport1 = new InMemoryTransport();
        $transport2 = new InMemoryTransport();
        $rails = array_map(static function ($job) { return new Rail($job); }, $jobs);

        $supervisor = new Supervisor($rails, null, $transport1, $transport2);

        Loop::repeat(1, static function () use ($supervisor, $transport2, $resultNumber) {
            $ips = $transport2->get();
            foreach ($ips as $ip) {
                $data = $ip->getMessage();
                self::assertEquals($resultNumber, $data['number']);

                $supervisor->stop();
            }
        });

        $envelope = Envelope::wrap(new ArrayObject(['number' => 0]), [new TransportMessageIdStamp('envelope_id')]);
        $transport1->send($envelope);

        $supervisor->run();
    }

    /**
     * @return array<array>
     */
    public function jobsProvider(): array
    {
        return [
            'oneJob' => [[static function (ArrayObject $data) {
                $data['number'] = 1;
            }], 1],
            'oneAsyncJob' => [[static function (ArrayObject $data) {
                yield delay(10);
                $data['number'] = 5;
            }], 5],
            'twoJob' => [[static function (ArrayObject $data) {
                $data['number'] += 2;
            }, static function (ArrayObject $data) {
                $data['number'] *= 3;
            }], 6],
            'twoAsyncJob' => [[static function (ArrayObject $data) {
                yield delay(10);
                $data['number'] += 5;
            }, static function (ArrayObject $data) {
                yield delay(10);
                $data['number'] *= 2;
            }], 10],
        ];
    }
}
