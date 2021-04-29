<?php

declare(strict_types=1);

namespace RFBP\Test\IpStrategy;

use PHPUnit\Framework\TestCase;
use RFBP\Ip;
use RFBP\IpStrategy\StackIpStrategy;

class StackIpStrategyTest extends TestCase
{
    public function testStrategy(): void
    {
        $ip1 = new Ip();
        $ip2 = new Ip();

        $strategy = new StackIpStrategy();
        $strategy->push($ip1);
        $strategy->push($ip2);

        $ip = $strategy->pop();
        self::assertNotNull($ip);
        self::assertSame($ip2, $ip);

        $ip = $strategy->pop();
        self::assertNotNull($ip);
        self::assertSame($ip1, $ip);

        $ip = $strategy->pop();
        self::assertNull($ip);
    }
}
