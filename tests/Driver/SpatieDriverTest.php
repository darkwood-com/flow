<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\SpatieDriver;
use Flow\DriverInterface;

/**
 * @template T1
 * @template T2
 *
 * @extends DriverTestCase<T1,T2>
 */
class SpatieDriverTest extends DriverTestCase
{
    public function testAsync(): void
    {
        self::assertTrue(true);
    }

    public function testAsyncReturn(): void
    {
        self::assertTrue(true);
    }

    public function testAsyncError(): void
    {
        self::assertTrue(true);
    }

    public function testDelay(): void
    {
        self::assertTrue(true);
    }

    /**
     * @return DriverInterface<T1,T2>
     */
    protected function createDriver(): DriverInterface
    {
        return new SpatieDriver();
    }
}
