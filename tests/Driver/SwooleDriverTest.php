<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\SwooleDriver;
use Flow\DriverInterface;
use Flow\Flow\Flow;
use Flow\Ip;

/**
 * @template T1
 * @template T2
 *
 * @extends DriverTestCase<T1,T2>
 */
class SwooleDriverTest extends DriverTestCase
{
    public function testNativeCoroutineRunsAFlow(): void
    {
        if (!extension_loaded('openswoole')) {
            self::markTestSkipped('OpenSwoole is not loaded in this shell.');
        }

        $result = null;
        $flow = new Flow(
            job: static function (int $value) use (&$result): void {
                $result = $value * 2;
            },
            driver: new SwooleDriver(),
        );

        $flow(new Ip(21));
        $flow->await();

        self::assertSame(42, $result);
    }

    /**
     * @return DriverInterface<T1,T2>
     */
    protected function createDriver(): DriverInterface
    {
        return new SwooleDriver();
    }
}
