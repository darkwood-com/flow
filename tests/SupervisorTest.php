<?php

namespace RFBP\Test;

use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use RFBP\Supervisor;
use Symfony\Component\Messenger\Transport\InMemoryTransport;
use RFBP\Rail;
use Symfony\Component\Messenger\Envelope as IP;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IPidStamp;
use function Amp\delay;

class SupervisorTest extends AsyncTestCase
{
    public function testIpWithoutId(): void
    {
        $transport1 = new InMemoryTransport();
        $transport2 = new InMemoryTransport();

        $supervisor = new Supervisor($transport1, $transport2, []);
        $ip = new Ip(new \stdClass());
        $transport1->send($ip);
        $this->expectException(\RuntimeException::class);
        $supervisor->run();
    }


    /**
     * @dataProvider jobsProvider
     * @param array<\Closure> $jobs
     * @param int $resultNumber
     */
    public function testRail(array $jobs, int $resultNumber): void
    {
        $transport1 = new InMemoryTransport();
        $transport2 = new InMemoryTransport();
        $rails = array_map(static function($job) { return new Rail($job); }, $jobs);

        $supervisor = new Supervisor($transport1, $transport2, $rails);

        Loop::repeat(1, static function() use ($supervisor, $transport2, $resultNumber) {
            $ips = $transport2->get();
            foreach ($ips as $ip) {
                $data = $ip->getMessage();
                self::assertEquals($resultNumber, $data['number']);

                $supervisor->stop();
            }
        });

        $ip = Ip::wrap(new \ArrayObject(['number' => 0]), [new IPidStamp('ip_id')]);
        $transport1->send($ip);

        $supervisor->run();
    }

    public function jobsProvider(): array
    {
        return [
            'oneJob' => [[static function (\ArrayObject $data) {
                $data['number'] = 1;
            }], 1],
        ];
    }
}
