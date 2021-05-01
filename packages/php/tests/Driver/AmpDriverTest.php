<?php

declare(strict_types=1);

namespace RFBP\Test\Driver;

use RFBP\Driver\AmpDriver;
use RFBP\DriverInterface;

class AmpDriverTest extends DriverTest
{
    protected function createDriver(): DriverInterface
    {
        return new AmpDriver();
    }
}
