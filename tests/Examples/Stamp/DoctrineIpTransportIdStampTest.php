<?php

declare(strict_types=1);

namespace Flow\Test\Examples\Stamp;

use Flow\Examples\Stamp\DoctrineIpTransportIdStamp;
use PHPUnit\Framework\TestCase;

class DoctrineIpTransportIdStampTest extends TestCase
{
    public function testId(): void
    {
        $stamp = new DoctrineIpTransportIdStamp('custom_id');
        self::assertSame('custom_id', $stamp->getId());
    }
}
