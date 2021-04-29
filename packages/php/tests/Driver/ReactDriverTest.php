<?php

declare(strict_types=1);

namespace RFBP\Test\Driver;

use RFBP\Driver\ReactDriver;
use RFBP\DriverInterface;

class ReactDriverTest extends DriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new ReactDriver();
    }
}
