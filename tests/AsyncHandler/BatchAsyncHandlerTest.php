<?php

declare(strict_types=1);

namespace Flow\Test\AsyncHandler;

use Flow\AsyncHandler\BatchAsyncHandler;
use Flow\Event\AsyncEvent;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

class BatchAsyncHandlerTest extends TestCase
{
    public function testAsyncEvent(): void
    {
        $result1 = null;
        $event1 = new AsyncEvent(static function (int $n1, int $n2) use (&$result1) {
            $result1 = $n1 + $n2;
        }, 2, 6);
        $result2 = null;
        $event2 = new AsyncEvent(static function (int $n1, int $n2) use (&$result2) {
            $result2 = $n1 + $n2;
        }, 6, 10);

        $asyncHandler = new BatchAsyncHandler(2);
        $asyncHandler->async($event1);
        assertSame(null, $result1);
        $asyncHandler->async($event2);
        assertSame(8, $result1);
        assertSame(16, $result2);
    }
}
