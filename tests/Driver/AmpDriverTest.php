<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\AmpDriver;
use Flow\DriverInterface;

class AmpDriverTest extends DriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new AmpDriver();
    }
}
