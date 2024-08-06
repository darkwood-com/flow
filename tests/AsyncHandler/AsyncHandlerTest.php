<?php

declare(strict_types=1);

namespace Flow\Test\AsyncHandler;

use Flow\AsyncHandler\AsyncHandler;
use Flow\Event\AsyncEvent;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

class AsyncHandlerTest extends TestCase
{
    public function testAsyncEvent(): void
    {
        $result = null;
        $event = new AsyncEvent(static function (int $n1, int $n2) use (&$result) {
            $result = $n1 + $n2;
        }, 2, 6);
        $asyncHandler = new AsyncHandler();
        $asyncHandler->async($event);
        assertSame(8, $result);
    }
}
