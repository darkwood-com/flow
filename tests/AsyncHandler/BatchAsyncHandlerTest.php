<?php

declare(strict_types=1);

namespace Flow\Test\AsyncHandler;

use Flow\AsyncHandler\BatchAsyncHandler;
use Flow\Event\AsyncEvent;
use Flow\Ip;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

class BatchAsyncHandlerTest extends TestCase
{
    public function testAsyncEvent(): void
    {
        $result1 = null;

        $event1 = new AsyncEvent(
            static fn ($x) => $x,
            static fn ($x) => $x,
            static function ($data) use (&$result1) {
                [$n1, $n2] = $data;
                $result1 = $n1 + $n2;

                return static function ($callback) use ($result1) {
                    $callback($result1);
                };
            },
            new Ip([2, 6]),
            static function () {}
        );

        $result2 = null;

        $event2 = new AsyncEvent(
            static fn ($x) => $x,
            static fn ($x) => $x,
            static function ($data) use (&$result2) {
                [$n1, $n2] = $data;
                $result2 = $n1 + $n2;

                return static function ($callback) use ($result2) {
                    $callback($result2);
                };
            },
            new Ip([6, 10]),
            static function () {}
        );

        $asyncHandler = new BatchAsyncHandler(2);
        $asyncHandler->async($event1);
        assertSame(null, $result1);
        $asyncHandler->async($event2);
        assertSame(8, $result1);
        assertSame(16, $result2);
    }
}
