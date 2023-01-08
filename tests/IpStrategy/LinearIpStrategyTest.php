<?php

declare(strict_types=1);

namespace Flow\Test\IpStrategy;

use Flow\Ip;
use Flow\IpStrategy\LinearIpStrategy;
use PHPUnit\Framework\TestCase;

class LinearIpStrategyTest extends TestCase
{
    public function testStrategy(): void
    {
        $ip1 = new Ip();
        $ip2 = new Ip();

        $strategy = new LinearIpStrategy();
        $strategy->push($ip1);
        $strategy->push($ip2);

        $ip = $strategy->pop();
        self::assertNotNull($ip);
        self::assertSame($ip1, $ip);

        $ip = $strategy->pop();
        self::assertNotNull($ip);
        self::assertSame($ip2, $ip);

        $ip = $strategy->pop();
        self::assertNull($ip);
    }
}
