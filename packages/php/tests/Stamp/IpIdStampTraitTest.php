<?php

declare(strict_types=1);

namespace RFBP\Test\Producer;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use RFBP\Stamp\IpIdStampTrait;
use stdClass;
use Symfony\Component\Messenger\Envelope as Ip;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IpIdStamp;

class IpIdStampTraitTest extends TestCase
{
    use IpIdStampTrait;

    public function testGetIpId(): void
    {
        $ip = Ip::wrap(new stdClass(), [new IpIdStamp('ip_id_test')]);
        $this->assertEquals('ip_id_test', $this->getIpId($ip));
    }

    public function testGetIpIdWithoutId(): void
    {
        $ip = new Ip(new stdClass());
        $this->expectException(RuntimeException::class);
        $this->getIpId($ip);
    }
}
