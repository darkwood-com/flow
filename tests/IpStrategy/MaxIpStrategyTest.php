<?php

declare(strict_types=1);

namespace Flow\Test\IpStrategy;

use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;
use PHPUnit\Framework\TestCase;

class MaxIpStrategyTest extends TestCase
{
    /**
     * @dataProvider strategyProvider
     */
    public function testStrategy(int $doneIndex): void
    {
        $strategy = new MaxIpStrategy(2);
        $strategy->push(new Ip());
        $strategy->push(new Ip());
        $strategy->push(new Ip());

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
     * @return array<array<mixed>>
     */
    public function strategyProvider(): array
    {
        return [
            [0],
            [1],
        ];
    }
}