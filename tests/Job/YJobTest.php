<?php

declare(strict_types=1);

namespace Flow\Test\Job;

use Closure;
use Flow\Job\YJob;
use PHPUnit\Framework\TestCase;

class YJobTest extends TestCase
{
    public function testJob(): void
    {
        $job = new YJob(static function (Closure $factorial) {
            return static function (int $n) use ($factorial) {
                return $n <= 1 ? 1 : $n * $factorial($n - 1);
            };
        });

        self::assertSame(24, $job(4));
    }
}
