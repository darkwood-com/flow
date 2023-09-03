<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\FiberDriver;
use Flow\DriverInterface;

class FiberDriverTest extends DriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new FiberDriver();
    }
}
