<?php

namespace RFBP\Test\Stamp;

use PHPUnit\Framework\TestCase;
use RFBP\Stamp\DoctrineIpTransportIdStamp;

class DoctrineIpTransportIdStampTest extends TestCase
{
    public function testId(): void
    {
        $stamp = new DoctrineIpTransportIdStamp('custom_id');
        self::assertSame('custom_id', $stamp->getId());
    }
}
