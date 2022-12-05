<?php

declare(strict_types=1);

namespace Flow\Test\Flow;

use ArrayObject;
use Flow\Driver\AmpDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SwooleDriver;
use Flow\DriverInterface;
use Flow\Ip;
use Flow\Flow\Flow;
use Flow\Flow\SequenceFlow;
use RuntimeException;

class SequenceFlowTest extends AbstractFlowTest
{
    /**
     * @dataProvider jobProvider
     */
    public function testJob(DriverInterface $driver): void
    {
        $ip = new Ip(new ArrayObject(['number' => 0]));
        $flows = [new Flow(function (object $data) {
            $data['number'] = 2;
        }, null, $driver), new Flow(function (object $data) {
            $data['number'] = 4;
        }, null, $driver)];
        $flow = new SequenceFlow($flows);
        $flow->pipe(function (Ip $ip) use ($driver) {
            $driver->stop();
            self::assertSame(4, $ip->getData()['number']);
        });
        ($flow)($ip);

        $driver->run();
    }

    /**
     * @return array<array<mixed>>
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
