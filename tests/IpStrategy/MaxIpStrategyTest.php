<?php

declare(strict_types=1);

namespace Flow\Test\IpStrategy;

use Flow\Event\PopEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;
use PHPUnit\Framework\TestCase;

class MaxIpStrategyTest extends TestCase
{
    /**
     * @dataProvider provideStrategyCases
     */
    public function testStrategy(int $doneIndex): void
    {
        $strategy = new MaxIpStrategy(2);
        $strategy->push(new PushEvent(new Ip()));
        $strategy->push(new PushEvent(new Ip()));
        $strategy->push(new PushEvent(new Ip()));

        $ips = [];

        $pullEvent = new PullEvent();
        $strategy->pull($pullEvent);
        $ips[] = $pullEvent->getIps()[0] ?? null;
        self::assertNotNull($ips[0]);

        $pullEvent = new PullEvent();
        $strategy->pull($pullEvent);
        $ips[] = $pullEvent->getIps()[0] ?? null;
        self::assertNotNull($ips[1]);

        $pullEvent = new PullEvent();
        $strategy->pull($pullEvent);
        $ips[] = $pullEvent->getIps()[0] ?? null;
        self::assertNull($ips[2]);

        $strategy->pop(new PopEvent($ips[$doneIndex]));

        $pullEvent = new PullEvent();
        $strategy->pull($pullEvent);
        $ips[] = $pullEvent->getIps()[0] ?? null;
        self::assertNotNull($ips[3]);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function provideStrategyCases(): iterable
    {
        return [
            [0],
            [1],
        ];
    }
}
