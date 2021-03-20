<?php

namespace RFBP\Test;

use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use RFBP\Supervisor;
use Symfony\Component\Messenger\Transport\InMemoryTransport;
use RFBP\Rail;
use Symfony\Component\Messenger\Envelope as IP;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IPidStamp;

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

    public function testValidIp(): void
    {
        $transport1 = new InMemoryTransport();
        $transport2 = new InMemoryTransport();
        $rails = [
            new Rail(static function (\ArrayObject $data) {
                $data['number'] = 1;
            })
        ];

        $supervisor = new Supervisor($transport1, $transport2, $rails);

        Loop::repeat(1, static function() use ($supervisor, $transport2) {
            $ips = $transport2->get();
            foreach ($ips as $ip) {
                $data = $ip->getMessage();
                self::assertEquals(1, $data['number']);

                $supervisor->stop();
            }
        });

        $ip = Ip::wrap(new \ArrayObject(['number' => 0]), [new IPidStamp('ip_id')]);
        $transport1->send($ip);

        $supervisor->run();
    }
}
