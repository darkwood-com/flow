<?php

declare(strict_types=1);

namespace RFBP\Test\IpStrategy;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use RFBP\Ip;
use RFBP\IpStrategy\MaxIpStrategy;

class MaxIpStrategyTest extends TestCase
{
    /**
     * @dataProvider strategyProvider
     */
    public function testStrategy(int $doneIndex): void
    {
        $strategy = new MaxIpStrategy(2);
        $strategy->push(new Ip(new ArrayObject(['data' => 1])));
        $strategy->push(new Ip(new ArrayObject(['data' => 2])));
        $strategy->push(new Ip(new ArrayObject(['data' => 3])));

        $ips = [];
        $ips[] = $strategy->pop();
        self::assertNotNull($ips[0]);
        $ips[] = $strategy->pop();
        self::assertNotNull($ips[1]);

        $ips[] = $strategy->pop();
        self::assertNull($ips[2]);

        $strategy->done($ips[$doneIndex]);

        $ips[] = $strategy->pop();
        self::assertNotNull($ips[3]);
    }

    /**
     * @return array<array>
     */
    public function strategyProvider(): array
    {
        return [
            [0],
            [1],
        ];
    }
}
