<?php

declare(strict_types=1);

namespace Flow\Test\AsyncHandler;

use Flow\AsyncHandler\AsyncHandler;
use Flow\Event\AsyncEvent;
use Flow\Ip;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

class AsyncHandlerTest extends TestCase
{
    public function testAsyncEvent(): void
    {
        $result = null;

        $event = new AsyncEvent(
            static fn ($x) => $x,
            static fn ($x) => $x,
            static function ($data) use (&$result) {
                [$n1, $n2] = $data;
                $result = $n1 + $n2;

                return static function ($callback) use ($result) {
                    $callback($result);
                };
            },
            new Ip([2, 6]),
            static function () {}
        );

        $asyncHandler = new AsyncHandler();
        $asyncHandler->async($event);
        assertSame(8, $result);
    }
}
