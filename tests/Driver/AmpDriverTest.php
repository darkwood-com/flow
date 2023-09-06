<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\AmpDriver;
use Flow\DriverInterface;
use Revolt\EventLoop;

class AmpDriverTest extends DriverTestCase
{
    protected function setUp(): void
    {
        EventLoop::getDriver()->run();
    }

    protected function tearDown(): void
    {
        EventLoop::getDriver()->stop();
    }

    protected function createDriver(): DriverInterface
    {
        return new AmpDriver();
    }
}
