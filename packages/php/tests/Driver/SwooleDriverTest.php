<?php

declare(strict_types=1);

namespace RFBP\Test\Driver;

use RFBP\Driver\SwooleDriver;
use RFBP\DriverInterface;

class SwooleDriverTest extends DriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new SwooleDriver();
    }
}
