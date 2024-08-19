<?php

declare(strict_types=1);

namespace Flow\Test\AsyncHandler;

use Flow\AsyncHandler\DeferAsyncHandler;
use Flow\Event\AsyncEvent;
use Flow\Ip;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertSame;

class DeferAsyncHandlerTest extends TestCase
{
    public function testAsyncEvent(): void
    {
        $result = null;

        $event = new AsyncEvent(
            static fn ($x) => $x,
            static fn ($x) => $x,
            static function ($args) use (&$result) {
                [[$n1, $n2], $defer] = $args;
                $result = $n1 + $n2;

                return static function ($callback) use ($result) {
                    $callback($result);
                };
            },
            new Ip([1, 7]),
            static function () {}
        );

        $asyncHandler = new DeferAsyncHandler();
        $asyncHandler->async($event);
        assertSame(8, $result);
    }
}
