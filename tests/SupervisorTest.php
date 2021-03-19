<?php

namespace RFBP\Test;

use Amp\Loop;
use RFBP\Supervisor;
use Symfony\Component\Messenger\Transport\InMemoryTransport;
use function Amp\delay;
use PHPUnit\Framework\TestCase;
use RFBP\Rail;
use Symfony\Component\Messenger\Envelope as IP;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IPidStamp;

class SupervisorTest extends TestCase
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
}
