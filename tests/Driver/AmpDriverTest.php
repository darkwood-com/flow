<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\AmpDriver;
use Flow\DriverInterface;

class AmpDriverTest extends DriverTestCase
{
    protected function createDriver(): DriverInterface
    {
        return new AmpDriver();
    }
}
