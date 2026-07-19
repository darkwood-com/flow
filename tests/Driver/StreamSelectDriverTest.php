<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\StreamSelectDriver;
use Flow\DriverInterface;
use Flow\FlowFactory;
use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;
use Generator;

/**
 * @template T1
 * @template T2
 *
 * @extends DriverTestCase<T1,T2>
 */
class StreamSelectDriverTest extends DriverTestCase
{
    public function testConcurrentSocketPairOverlap(): void
    {
        $driver = new StreamSelectDriver();

        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertNotFalse($sockets);
        [$left, $right] = $sockets;
        stream_set_blocking($left, false);
        stream_set_blocking($right, false);

        $order = [];

        $flow = (new FlowFactory($driver))->create(static function () use ($driver, &$order, $left, $right) {
            yield [static function (string $side) use ($driver, &$order, $left, $right): Generator {
                if ($side === 'writer') {
                    yield $driver->waitDelay(0.05);
                    fwrite($right, 'ping');
                    $order[] = 'written';

                    return 'written';
                }

                $order[] = 'wait-start';
                $ready = (yield $driver->waitReadable($left, 1.0));
                self::assertTrue($ready);
                $payload = fread($left, 16);
                $order[] = 'read:' . $payload;

                return (string) $payload;
            }, null, new MaxIpStrategy(2)];
        });

        $flow(new Ip('reader'));
        $flow(new Ip('writer'));
        $flow->await();

        fclose($left);
        fclose($right);

        self::assertSame(['wait-start', 'written', 'read:ping'], $order);
    }

    /**
     * @return DriverInterface<T1,T2>
     */
    protected function createDriver(): DriverInterface
    {
        return new StreamSelectDriver();
    }
}
