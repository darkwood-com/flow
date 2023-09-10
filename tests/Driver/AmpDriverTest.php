<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\AmpDriver;
use Flow\DriverInterface;

/**
 * @template T1
 * @template T2
 *
 * @extends DriverTestCase<T1,T2>
 */
class AmpDriverTest extends DriverTestCase
{
    /**
     * @return DriverInterface<T1,T2>
     */
    protected function createDriver(): DriverInterface
    {
        return new AmpDriver();
    }
}
