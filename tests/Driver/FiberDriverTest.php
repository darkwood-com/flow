<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Fiber;
use Flow\Driver\FiberDriver;
use Flow\DriverInterface;

/**
 * @template T1
 * @template T2
 *
 * @extends DriverTestCase<T1,T2>
 */
class FiberDriverTest extends DriverTestCase
{
    public function testDeferPreservesAnImmediateResolution(): void
    {
        $driver = new FiberDriver();

        self::assertSame('resolved', $driver->defer(static function ($complete): void {
            $complete('resolved');
        }));
    }

    public function testFractionalTimersKeepIndependentIdentifiersAndCanBeCancelledTwice(): void
    {
        $driver = new FiberDriver();
        $calls = [];

        $cancelDiscarded = $driver->tick(0.001, static function (): void {});
        $cancelDiscarded();
        $cancelDiscarded();

        $cancelB = null;
        $cancelB = $driver->tick(0.001, static function () use (&$calls, &$cancelB): void {
            $calls[] = 'b';
            $cancelB();
        });

        $cancelC = null;
        $cancelC = $driver->tick(0.001, static function () use (&$calls, &$cancelC): void {
            $calls[] = 'c';
            $cancelC();
        });

        $stream = ['fnFlows' => [], 'dispatchers' => []];
        $driver->await($stream);

        sort($calls);
        self::assertSame(['b', 'c'], $calls);
    }

    public function testFractionalDelayUsesSubSecondPrecision(): void
    {
        while (microtime(true) - floor(microtime(true)) > 0.05) {
            usleep(1_000);
        }

        $driver = new FiberDriver();
        $fiber = new Fiber(static function () use ($driver): void {
            $driver->delay(0.01);
        });
        $start = hrtime(true);

        $fiber->start();
        while ($fiber->isSuspended()) {
            $fiber->resume();
        }

        $elapsed = (hrtime(true) - $start) / 1_000_000_000;
        self::assertGreaterThanOrEqual(0.005, $elapsed);
        self::assertLessThan(0.2, $elapsed);
    }

    /**
     * @return DriverInterface<T1,T2>
     */
    protected function createDriver(): DriverInterface
    {
        return new FiberDriver();
    }
}
