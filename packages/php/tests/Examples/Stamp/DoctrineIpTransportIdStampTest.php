<?php

declare(strict_types=1);

namespace Flow\Test\Examples\Stamp;

use PHPUnit\Framework\TestCase;
use Flow\Examples\Stamp\DoctrineIpTransportIdStamp;

class DoctrineIpTransportIdStampTest extends TestCase
{
    public function testId(): void
    {
        $stamp = new DoctrineIpTransportIdStamp('custom_id');
        self::assertSame('custom_id', $stamp->getId());
    }
}
