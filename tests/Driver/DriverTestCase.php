<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Exception;
use Flow\DriverInterface;
use Flow\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * @template T1
 * @template T2
 */
abstract class DriverTestCase extends TestCase
{
    /*public function testAsync(): void
    {
        $driver = $this->createDriver();
        $value = $driver->await($driver->async(static function () {})());
        self::assertNull($value);
    }

    public function testAsyncReturn(): void
    {
        $driver = $this->createDriver();
        $value = $driver->await($driver->async(static function () {
            return 2;
        })());
        self::assertSame(2, $value);
    }

    public function testAsyncError(): void
    {
        $driver = $this->createDriver();
        $value = $driver->await($driver->async(static function () {
            throw new Exception();
        })());
        self::assertInstanceOf(RuntimeException::class, $value);
        self::assertInstanceOf(Exception::class, $value->getPrevious());
    }

    public function testDelay(): void
    {
        $driver = $this->createDriver();
        $value = $driver->await($driver->async(static function () use ($driver) {
            $driver->delay(1 / 1000);
        })());
        self::assertNull($value);
    }

    public function testTick(): void
    {
        $i = 0;
        $driver = $this->createDriver();
        $cancel = $driver->tick(1 / 1000, static function () use (&$i) {
            $i++;
        });
        $driver->await($driver->async(static function () use ($driver) {
            $driver->delay(5 / 1000);
        })());
        $cancel();

        self::assertGreaterThan(3, $i);
    }*/

    /**
     * @return DriverInterface<T1,T2>
     */
    abstract protected function createDriver(): DriverInterface;
}
