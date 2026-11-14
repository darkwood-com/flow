<?php

declare(strict_types=1);

namespace Flow\Test\IpStrategy;

use Flow\Event;
use Flow\Event\PopEvent;
use Flow\Event\PullEvent;
use Flow\Event\PushEvent;
use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class MaxIpStrategyTest extends TestCase
{
    #[DataProvider('provideStrategyCases')]
    public function testStrategy(int $doneIndex)
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

        $strategy->release();
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

    public function testReleaseAndObservablePopAreIndependent(): void
    {
        $innerDispatcher = new EventDispatcher();
        $pops = 0;
        $innerDispatcher->addListener(Event::POP, static function () use (&$pops): void {
            $pops++;
        });
        $strategy = new MaxIpStrategy(1, null, $innerDispatcher);
        $strategy->push(new PushEvent(new Ip('first')));
        $strategy->push(new PushEvent(new Ip('second')));

        $firstPull = new PullEvent();
        $strategy->pull($firstPull);
        $first = $firstPull->getIps()[0];

        $strategy->release();
        $strategy->release();
        self::assertSame(0, $pops);

        $secondPull = new PullEvent();
        $strategy->pull($secondPull);
        self::assertSame('second', $secondPull->getIps()[0]->data);

        $strategy->pop(new PopEvent($first));
        self::assertSame(1, $pops);
    }
}
