<?php

declare(strict_types=1);

namespace Flow\Test\AsyncHandler;

use Flow\AsyncHandler\AsyncHandler;
use Flow\Event\AsyncEvent;
use Flow\Event\PoolEvent;
use Flow\Ip;
use LogicException;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

class AsyncHandlerTest extends TestCase
{
    public function testAsyncEvent()
    {
        $result = null;

        $event = new AsyncEvent(
            static fn ($x) => $x,
            static fn ($x) => $x,
            static function ($data) use (&$result) {
                [$n1, $n2] = $data;
                $result = $n1 + $n2;

                return static function ($callback) use ($result): void {
                    $callback($result);
                };
            },
            new Ip([2, 6]),
            static function (): void {}
        );

        $asyncHandler = new AsyncHandler();
        $asyncHandler->async($event);
        assertSame(8, $result);
    }

    public function testCallbackFailureReleasesTheActiveIp(): void
    {
        $handler = new AsyncHandler();
        $event = new AsyncEvent(
            static fn ($job) => static fn ($data) => static function ($next) use ($job, $data): void {
                $next($job($data));
            },
            static fn ($job) => $job,
            static fn ($data) => $data,
            new Ip('value'),
            static function (): never {
                throw new LogicException('callback failed');
            },
        );

        try {
            $handler->async($event);
            self::fail('The callback exception was not propagated.');
        } catch (LogicException $exception) {
            self::assertSame('callback failed', $exception->getMessage());
        }

        $pool = new PoolEvent();
        $handler->pool($pool);
        self::assertSame([], $pool->getIps());
    }

    public function testContinuationCanOnlyCompleteOnce(): void
    {
        $calls = 0;
        $handler = new AsyncHandler();
        $event = new AsyncEvent(
            static fn ($job) => static fn ($data) => static function ($next) use ($job, $data): void {
                $next($job($data));
                $next($job($data));
            },
            static fn ($job) => $job,
            static fn ($data) => $data,
            new Ip('value'),
            static function () use (&$calls): void {
                $calls++;
            },
        );

        $this->expectException(LogicException::class);

        try {
            $handler->async($event);
        } finally {
            $pool = new PoolEvent();
            $handler->pool($pool);
            self::assertSame(1, $calls);
            self::assertSame([], $pool->getIps());
        }
    }

    public function testFailureBeforeContinuationIsInstalledReleasesTheActiveIp(): void
    {
        $handler = new AsyncHandler();
        $event = new AsyncEvent(
            static function (): never {
                throw new LogicException('continuation was not installed');
            },
            static fn ($job) => $job,
            static fn ($data) => $data,
            new Ip('value'),
            static function (): void {},
        );

        $this->expectExceptionMessage('continuation was not installed');

        try {
            $handler->async($event);
        } finally {
            $pool = new PoolEvent();
            $handler->pool($pool);
            self::assertSame([], $pool->getIps());
        }
    }

    public function testFailureAfterCompletionDoesNotReleaseTwice(): void
    {
        $calls = 0;
        $handler = new AsyncHandler();
        $event = new AsyncEvent(
            static fn ($job) => static fn ($data) => static function ($next) use ($job, $data): never {
                $next($job($data));

                throw new LogicException('failed after completion');
            },
            static fn ($job) => $job,
            static fn ($data) => $data,
            new Ip('value'),
            static function () use (&$calls): void {
                $calls++;
            },
        );

        $this->expectExceptionMessage('failed after completion');

        try {
            $handler->async($event);
        } finally {
            $pool = new PoolEvent();
            $handler->pool($pool);
            self::assertSame(1, $calls);
            self::assertSame([], $pool->getIps());
        }
    }
}
