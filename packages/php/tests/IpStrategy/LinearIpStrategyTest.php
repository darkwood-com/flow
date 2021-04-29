<?php

declare(strict_types=1);

namespace RFBP\Test\IpStrategy;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use RFBP\IpStrategy\LinearIpStrategy;
use Symfony\Component\Messenger\Envelope as Ip;

class LinearIpStrategyTest extends TestCase
{
    public function testStrategy(): void
    {
        $strategy = new LinearIpStrategy();
        $strategy->push(Ip::wrap(new ArrayObject(['data' => 1])));
        $strategy->push(Ip::wrap(new ArrayObject(['data' => 2])));

        $ip = $strategy->pop();
        self::assertNotNull($ip);
        self::assertSame(1, $ip->getMessage()['data']);

        $ip = $strategy->pop();
        self::assertNotNull($ip);
        self::assertSame(2, $ip->getMessage()['data']);

        $ip = $strategy->pop();
        self::assertNull($ip);
    }
}
