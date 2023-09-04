<?php

declare(strict_types=1);

namespace Flow\Test;

use Flow\EnvelopeTrait;
use PHPUnit\Framework\TestCase;
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
        self::assertSame('envelope_id_test', $this->getEnvelopeId($ip));
    }

    public function testGetIpIdWithoutId(): void
    {
        $envelope = new Envelope(new stdClass());
        $this->expectException(RuntimeException::class);
        $this->getEnvelopeId($envelope);
    }
}
