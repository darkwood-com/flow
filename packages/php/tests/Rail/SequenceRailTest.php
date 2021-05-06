<?php

declare(strict_types=1);

namespace RFBP\Test\Rail;

use ArrayObject;
use RFBP\Driver\AmpDriver;
use RFBP\Driver\ReactDriver;
use RFBP\Driver\SwooleDriver;
use RFBP\DriverInterface;
use RFBP\Ip;
use RFBP\Rail\Rail;
use RFBP\Rail\SequenceRail;
use RuntimeException;

class SequenceRailTest extends AbstractRailTest
{
    /**
     * @dataProvider jobProvider
     */
    public function testJob(DriverInterface $driver): void
    {
        $ip = new Ip(new ArrayObject(['number' => 0]));
        $rails = [new Rail(function (object $data) {
            $data['number'] = 2;
        }, null, $driver), new Rail(function (object $data) {
            $data['number'] = 4;
        }, null, $driver)];
        $rail = new SequenceRail($rails);
        $rail->pipe(function (Ip $ip) use ($driver) {
            $driver->stop();
            self::assertSame(4, $ip->getData()['number']);
        });
        ($rail)($ip);

        $driver->run();
    }

    /**
     * @return array<array>
     */
    public function jobProvider(): array
    {
        return [
            [new AmpDriver()],
            [new ReactDriver()],
            [new SwooleDriver()],
        ];
    }
}
