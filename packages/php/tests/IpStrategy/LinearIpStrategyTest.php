<?php

declare(strict_types=1);

namespace RFBP\Test\IpStrategy;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use RFBP\Ip;
use RFBP\IpStrategy\LinearIpStrategy;

class LinearIpStrategyTest extends TestCase
{
    public function testStrategy(): void
    {
        $strategy = new LinearIpStrategy();
        $strategy->push(new Ip(new ArrayObject(['data' => 1])));
        $strategy->push(new Ip(new ArrayObject(['data' => 2])));

        $ip = $strategy->pop();
        self::assertNotNull($ip);
        self::assertSame(1, $ip->getData()['data']);

        $ip = $strategy->pop();
        self::assertNotNull($ip);
        self::assertSame(2, $ip->getData()['data']);

        $ip = $strategy->pop();
        self::assertNull($ip);
    }
}
