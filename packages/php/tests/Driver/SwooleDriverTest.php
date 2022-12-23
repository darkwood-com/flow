<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\SwooleDriver;
use Flow\DriverInterface;

class SwooleDriverTest extends DriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new SwooleDriver();
    }
}
