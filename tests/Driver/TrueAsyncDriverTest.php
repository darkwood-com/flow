<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\TrueAsyncDriver;
use Flow\DriverInterface;

/**
 * @template T1
 * @template T2
 *
 * @extends DriverTestCase<T1,T2>
 */
final class TrueAsyncDriverTest extends DriverTestCase
{
    /**
     * @return DriverInterface<T1,T2>
     */
    protected function createDriver(): DriverInterface
    {
        if (!TrueAsyncDriver::isSupported()) {
            self::markTestSkipped('ext-async not loaded');
        }

        return new TrueAsyncDriver();
    }
}
