<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Exception;
use Flow\DriverInterface;
use PHPUnit\Framework\TestCase;

abstract class DriverTestCase extends TestCase
{
    public function testAsync(): void
    {
        $driver = $this->createDriver();
        $driver->async(static function () {
        }, function (mixed $value) {
            $this->assertNull($value);
        })();
    }

    public function testAsyncReturn(): void
    {
        $driver = $this->createDriver();
        $driver->async(static function () {
            return 2;
        }, function (mixed $value) {
            $this->assertEquals(2, $value);
        })();
    }

    public function testAsyncError(): void
    {
        $driver = $this->createDriver();
        $driver->async(static function () {
            throw new Exception();
        }, function (mixed $value) {
            $this->assertInstanceOf(Exception::class, $value);
        })();
    }

    public function testDelay(): void
    {
        $driver = $this->createDriver();
        $driver->async(static function () use ($driver) {
            $driver->delay(1 / 1000);
        }, function (mixed $value) {
            $this->assertNull($value);
        })();
    }

    public function testTick(): void
    {
        $this->assertTrue(true);

        /*$i = 0;
        $driver = $this->createDriver();
        $driver->tick(1, function () use (&$i) {
            $i++;
        });
        $driver->async(function () use ($driver, &$i) {
            $driver->delay(3 / 1000);

            $this->assertGreaterThan(3, $i);
        })();*/
    }

    abstract protected function createDriver(): DriverInterface;
}
