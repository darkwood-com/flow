<?php

declare(strict_types=1);

namespace Flow\Test\AsyncHandler;

use Flow\AsyncHandler\DeferAsyncHandler;
use Flow\Event\AsyncEvent;
use Flow\Event\PoolEvent;
use Flow\Ip;
use LogicException;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

class DeferAsyncHandlerTest extends TestCase
{
    public function testAsyncEvent()
    {
        $result = null;

        $event = new AsyncEvent(
            static fn ($x) => $x,
            static fn ($x) => $x,
            static function (array $args) use (&$result) {
                [[$n1, $n2], $defer] = $args;
                $result = $n1 + $n2;

                return static function ($callback) use ($result): void {
                    $callback([$result]);
                };
            },
            new Ip([1, 7]),
            static function (): void {}
        );

        $asyncHandler = new DeferAsyncHandler();
        $asyncHandler->async($event);
        assertSame(8, $result);
    }

    public function testCallbackFailureReleasesTheActiveIp(): void
    {
        $handler = new DeferAsyncHandler();
        $event = new AsyncEvent(
            static fn ($job) => $job,
            static fn ($job) => $job,
            static fn (array $args) => static function ($next) use ($args): void {
                $next([$args[0]]);
            },
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
        $handler = new DeferAsyncHandler();
        $event = new AsyncEvent(
            static fn ($job) => $job,
            static fn ($job) => $job,
            static fn (array $args) => static function ($next) use ($args): void {
                $next([$args[0]]);
                $next([$args[0]]);
            },
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
}
