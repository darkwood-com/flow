<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\RevoltDriver;
use Flow\DriverInterface;
use Revolt\EventLoop;

/**
 * @template T1
 * @template T2
 *
 * @extends DriverTestCase<T1,T2>
 */
class RevoltDriverTest extends DriverTestCase
{
    protected function setUp(): void
    {
        EventLoop::getDriver()->run();
    }

    protected function tearDown(): void
    {
        EventLoop::getDriver()->stop();
    }

    /**
     * @return DriverInterface<T1,T2>
     */
    protected function createDriver(): DriverInterface
    {
        return new RevoltDriver();
    }
}
