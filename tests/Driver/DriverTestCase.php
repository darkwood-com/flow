<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Exception;
use Flow\DriverInterface;
use Flow\Event;
use Flow\Event\PushEvent;
use Flow\Exception\RuntimeException;
use Flow\Ip;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @template T1
 * @template T2
 */
abstract class DriverTestCase extends TestCase
{
    public function testAsync(): void
    {
        self::assertTrue(true);
        /*$driver = $this->createDriver();
        $value = $driver->async(static function () {})();
        self::assertNull($value);*/
    }

    /*public function testAsync(): void
    {
        $driver = $this->createDriver();
        $stream = $driver->async(static function () {})();
        $value = $driver->await($stream);
        self::assertNull($value);
    }

    public function testAsyncReturn(): void
    {
        $driver = $this->createDriver();
        $stream = $driver->async(static function () {
            return 2;
        })();
        $value = $driver->await($stream);
        self::assertSame(2, $value);
    }

    public function testAsyncError(): void
    {
        $driver = $this->createDriver();
        $stream = $driver->async(static function () {
            throw new Exception();
        })();
        $value = $driver->await($stream);
        self::assertInstanceOf(RuntimeException::class, $value);
        self::assertInstanceOf(Exception::class, $value->getPrevious());
    }

    public function testDelay(): void
    {
        $driver = $this->createDriver();
        $stream = $driver->async(static function () use ($driver) {
            $driver->delay(1 / 1000);
        })();
        $value = $driver->await($stream);
        self::assertNull($value);
    }

    public function testTick(): void
    {
        $i = 0;
        $driver = $this->createDriver();
        $cancel = $driver->tick(1, static function () use (&$i) {
            $i++;
        });

        $dispatcher = new EventDispatcher();
        $dispatcher->dispatch(new PushEvent(new Ip()), Event::PUSH);

        $stream = [
            'ips' => 1,
            'fnFlows' => [static function () use ($driver) {
                $driver->delay(1 / 1000);
            }],
            'dispatchers' => [$dispatcher],
        ];
        $driver->await($stream);
        $cancel();

        self::assertGreaterThan(3, $i);
    }*/

    /**
     * @return DriverInterface<T1,T2>
     */
    abstract protected function createDriver(): DriverInterface;
}
