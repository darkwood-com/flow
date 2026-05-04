<?php

declare(strict_types=1);

namespace Flow\Test\Job;

use Flow\Job\ClosureJob;
use PHPUnit\Framework\TestCase;

class ClosureJobTest extends TestCase
{
    public function testJob(): void
    {
        $job = new ClosureJob(static function ($n) {
            return $n + 1;
        });
        self::assertSame(3, $job(2));
    }
}
