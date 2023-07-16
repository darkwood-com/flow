<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Exception;
use Flow\DriverInterface;
use PHPUnit\Framework\TestCase;
use Throwable;

abstract class DriverTest extends TestCase
{
    public function testAsync(): void
    {
        $driver = $this->createDriver();
        $driver->async(static function () {
        }, function (?Throwable $e) {
            $this->assertNull($e);
        })();
    }

    public function testAsyncError(): void
    {
        $driver = $this->createDriver();
        $driver->async(static function () {
            throw new Exception();
        }, function (?Throwable $e) {
            $this->assertNotNull($e);
        })();
    }

    public function testDelay(): void
    {
        $driver = $this->createDriver();
        $driver->async(static function () use ($driver) {
            $driver->delay(1 / 1000);
        }, function (?Throwable $e) {
            $this->assertNull($e);
        })();
    }

    /*public function testTick(): void
    {
        $i = 0;
        $driver = $this->createDriver();
        $driver->tick(1, function () use (&$i) {
            $i++;
        });
        $driver->async(function () use ($driver, &$i) {
            $driver->delay(3 / 1000);

            $this->assertGreaterThan(3, $i);
        })();
    }*/

    abstract protected function createDriver(): DriverInterface;
}
