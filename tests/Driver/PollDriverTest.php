<?php

declare(strict_types=1);

namespace Flow\Test\Driver;

use Flow\Driver\PollDriver;
use Flow\Driver\StreamSelectDriver;
use Flow\Flow\Flow;
use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;
use Generator;
use Io\Poll\InvalidHandleException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StreamErrorCode;
use StreamErrorMode;
use StreamErrorStore;

final class PollDriverTest extends TestCase
{
    /** @return iterable<string, array{PollDriver<mixed, mixed>|StreamSelectDriver<mixed, mixed>}> */
    public static function streamDrivers(): iterable
    {
        yield 'native poll' => [new PollDriver()];
        yield 'legacy select' => [new StreamSelectDriver()];
    }

    /** @param PollDriver<mixed, mixed>|StreamSelectDriver<mixed, mixed> $driver */
    #[DataProvider('streamDrivers')]
    public function testReadWriteAndResubscribeAcrossTwoSocketPairs(PollDriver|StreamSelectDriver $driver): void
    {
        [$firstReader, $firstWriter] = self::socketPair();
        [$secondReader, $secondWriter] = self::socketPair();

        try {
            $results = [];
            $flow = new Flow(
                job: static function (string $task) use ($driver, $firstReader, $firstWriter, $secondReader, $secondWriter): Generator {
                    if ($task === 'write') {
                        yield $driver->waitDelay(0.015);
                        if (yield $driver->waitWritable($firstWriter, 0.5)) {
                            fwrite($firstWriter, 'ab');
                        }
                        fclose($secondWriter);

                        return 'written';
                    }
                    if ($task === 'eof') {
                        return (yield $driver->waitReadable($secondReader, 0.5)) && fread($secondReader, 1) === '';
                    }

                    if (!(yield $driver->waitReadable($firstReader, 0.5))) {
                        return false;
                    }
                    $first = fread($firstReader, 1);

                    return (yield $driver->waitReadable($firstReader, 0.5)) ? $first . fread($firstReader, 1) : false;
                },
                ipStrategy: new MaxIpStrategy(3),
                driver: $driver,
            )->fn(static function (mixed $result) use (&$results): void {
                $results[] = $result;
            });

            foreach (['read', 'write', 'eof'] as $task) {
                $flow(new Ip($task));
            }
            $flow->await();

            self::assertContains('written', $results);
            self::assertContains('ab', $results);
            self::assertContains(true, $results);
            self::assertTrue(is_resource($firstReader));
        } finally {
            foreach ([$firstReader, $firstWriter, $secondReader, $secondWriter] as $socket) {
                if (is_resource($socket)) {
                    fclose($socket);
                }
            }
        }
    }

    /** @param PollDriver<mixed, mixed>|StreamSelectDriver<mixed, mixed> $driver */
    #[DataProvider('streamDrivers')]
    public function testTimeoutCanImmediatelyRegisterAnotherWait(PollDriver|StreamSelectDriver $driver): void
    {
        [$reader, $writer] = self::socketPair();
        try {
            $result = null;
            $flow = new Flow(
                job: static function () use ($driver, $reader): Generator {
                    $first = yield $driver->waitReadable($reader, 0.015);
                    $second = yield $driver->waitReadable($reader, 0.015);

                    return [$first, $second];
                },
                driver: $driver,
            )->fn(static function (array $value) use (&$result): void {
                $result = $value;
            });

            $started = hrtime(true);
            $flow(new Ip());
            $flow->await();

            self::assertSame([false, false], $result);
            self::assertGreaterThanOrEqual(28_000_000, hrtime(true) - $started);
        } finally {
            fclose($reader);
            fclose($writer);
        }
    }

    /** @param PollDriver<mixed, mixed>|StreamSelectDriver<mixed, mixed> $driver */
    #[DataProvider('streamDrivers')]
    public function testTickUsesFractionalSecondsAndCanCancelItself(PollDriver|StreamSelectDriver $driver): void
    {
        $calls = 0;
        $cancel = $driver->tick(0.012, static function () use (&$calls, &$cancel): void {
            ++$calls;
            $cancel();
        });
        $flow = new Flow(static fn () => null, driver: $driver);
        $started = hrtime(true);
        $flow->await();

        self::assertSame(1, $calls);
        self::assertGreaterThanOrEqual(10_000_000, hrtime(true) - $started);
    }

    public function testClosedWatchedStreamFailsAsInfrastructureAndDriverCanBeReused(): void
    {
        [$reader, $writer] = self::socketPair();
        $driver = new PollDriver();
        $jobErrors = [];
        $results = [];
        try {
            $flow = new Flow(
                job: static function (string $task) use ($driver, $reader): Generator {
                    if ($task === 'close') {
                        yield $driver->waitDelay(0.01);
                        fclose($reader);

                        return 'closed';
                    }
                    if ($task === 'retry') {
                        return 'recovered';
                    }

                    return yield $driver->waitReadable($reader);
                },
                errorJob: static function ($error) use (&$jobErrors): void {
                    $jobErrors[] = $error;
                },
                ipStrategy: new MaxIpStrategy(2),
                driver: $driver,
            )->fn(static function (string $result) use (&$results): void {
                $results[] = $result;
            });

            $flow(new Ip('wait'));
            $flow(new Ip('close'));
            try {
                $flow->await();
                self::fail('Expected an infrastructure failure.');
            } catch (InvalidHandleException) {
                self::assertSame([], $jobErrors);
            }

            $flow(new Ip('retry'));
            $flow->await();
            self::assertSame(['recovered'], $results);
        } finally {
            fclose($writer);
        }
    }

    public function testUnsupportedHandleFailsAsInfrastructure(): void
    {
        $driver = new PollDriver();
        $memory = fopen('php://memory', 'r+', context: stream_context_create([
            'stream' => ['error_mode' => StreamErrorMode::Exception, 'error_store' => StreamErrorStore::All],
        ]));
        self::assertIsResource($memory);
        try {
            $flow = new Flow(
                job: static function () use ($driver, $memory): Generator {
                    return yield $driver->waitReadable($memory, 0.5);
                },
                driver: $driver,
            );

            $flow(new Ip());
            try {
                $flow->await();
                self::fail('Expected an unsupported descriptor to fail.');
            } catch (InvalidHandleException) {
                self::assertSame([StreamErrorCode::CastNotSupported], array_column(stream_last_errors(), 'code'));
            }
        } finally {
            fclose($memory);
            stream_clear_errors();
        }
    }

    /** @return array{resource, resource} */
    private static function socketPair(): array
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        self::assertNotFalse($sockets);
        foreach ($sockets as $socket) {
            stream_set_blocking($socket, false);
        }

        return $sockets;
    }
}
