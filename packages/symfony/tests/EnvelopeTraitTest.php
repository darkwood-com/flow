<?php

declare(strict_types=1);

namespace RFBP\Test;

use PHPUnit\Framework\TestCase;
use RFBP\EnvelopeTrait;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class EnvelopeTraitTest extends TestCase
{
    use EnvelopeTrait;

    public function testGetIpId(): void
    {
        $ip = Envelope::wrap(new stdClass(), [new TransportMessageIdStamp('envelope_id_test')]);
        self::assertEquals('envelope_id_test', $this->getEnvelopeId($ip));
    }

    public function testGetIpIdWithoutId(): void
    {
        $envelope = new Envelope(new stdClass());
        $this->expectException(RuntimeException::class);
        $this->getEnvelopeId($envelope);
    }
}
