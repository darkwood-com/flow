<?php

declare(strict_types=1);

namespace Flow\Test\IpStrategy;

use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
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
        $strategy->push(new PushEvent($ip1));
        $strategy->push(new PushEvent($ip2));

        $pullEvent = new PullEvent();
        $strategy->pull($pullEvent);
        self::assertNotNull($pullEvent->getIp());
        self::assertSame($ip1, $pullEvent->getIp());

        $pullEvent = new PullEvent();
        $strategy->pull($pullEvent);
        self::assertNotNull($pullEvent->getIp());
        self::assertSame($ip2, $pullEvent->getIp());

        $pullEvent = new PullEvent();
        $strategy->pull($pullEvent);
        self::assertNull($pullEvent->getIp());
    }
}
