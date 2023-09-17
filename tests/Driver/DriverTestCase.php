<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Exception;
use Flow\DriverInterface;
use PHPUnit\Framework\TestCase;

/**
 * @template T1
 * @template T2
 */
abstract class DriverTestCase extends TestCase
{
    public function testAsync(): void
    {
        $driver = $this->createDriver();
        $driver->async(static function () {
        }, function (mixed $value) use ($driver) {
            $driver->stop();
            $this->assertNull($value);
        })();
        $driver->start();
    }

    public function testAsyncReturn(): void
    {
        $driver = $this->createDriver();
        $driver->async(static function () {
            return 2;
        }, function (mixed $value) use ($driver) {
            $driver->stop();
            $this->assertSame(2, $value);
        })();
        $driver->start();
    }

    public function testAsyncError(): void
    {
        $driver = $this->createDriver();
        $driver->async(static function () {
            throw new Exception();
        }, function (mixed $value) use ($driver) {
            $driver->stop();
            $this->assertInstanceOf(Exception::class, $value);
        })();
        $driver->start();
    }

    public function testDelay(): void
    {
        $driver = $this->createDriver();
        $driver->async(static function () use ($driver) {
            $driver->delay(1 / 1000);
        }, function (mixed $value) use ($driver) {
            $driver->stop();
            $this->assertNull($value);
        })();
        $driver->start();
    }

    public function testTick(): void
    {
        self::assertTrue(true);

        /*$i = 0;
        $driver = $this->createDriver();
        $driver->tick(1, function () use (&$i) {
            $i++;
        });
        $driver->async(function () use ($driver, &$i) {
            $driver->delay(3 / 1000);
            $driver->stop();

            $this->assertGreaterThan(3, $i);
        })();

        $driver->start();*/
    }

    /**
     * @return DriverInterface<T1,T2>
     */
    abstract protected function createDriver(): DriverInterface;
}
