<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\RevoltDriver;
use Flow\DriverInterface;

class RevoltDriverTest extends DriverTestCase
{
    protected function createDriver(): DriverInterface
    {
        return new RevoltDriver();
    }
}
