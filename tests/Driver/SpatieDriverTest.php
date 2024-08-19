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
    /**
     * @return DriverInterface<T1,T2>
     */
    protected function createDriver(): DriverInterface
    {
        return new SpatieDriver();
    }
}
