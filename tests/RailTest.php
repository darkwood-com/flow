<?php

namespace RFBP\Test;

use Amp\Loop;
use function Amp\delay;
use PHPUnit\Framework\TestCase;
use RFBP\Rail;
use Symfony\Component\Messenger\Envelope as IP;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IPidStamp;

class RailTest extends TestCase
{
    public function testIpWithoutId(): void
    {
        $job = static function() {};
        $ip = new Ip(new \stdClass());
        $rail = new Rail($job);
        $this->expectException(\RuntimeException::class);
        ($rail)($ip);
    }

    public function testSyncJob(): void
    {
        $job = static function(\ArrayObject $data): \Callable {
            $data['number'] = 1;
        };
        $ip = Ip::wrap(new \ArrayObject(['number' => 0]), [new IPidStamp('ip_id')]);
        $rail = new Rail($job);
        $rail->pipe(function(IP $ip) {
            self::assertSame(1, $ip->getMessage()['number']);
        });
        ($rail)($ip);
    }

    public function testAsyncJob(): void
    {
        $job = static function(\ArrayObject $data): \Generator {
            yield delay(10);
            $data['number'] = 1;
        };
        $ip = Ip::wrap(new \ArrayObject(['number' => 0]), [new IPidStamp('ip_id')]);
        $rail = new Rail($job);
        $rail->pipe(function(IP $ip) {
            self::assertSame(1, $ip->getMessage()['number']);
        });
        ($rail)($ip);
        Loop::run();
    }
}
