<?php

declare(strict_types=1);

namespace Flow;

use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

trait EnvelopeTrait
{
    private function getEnvelopeId(Envelope $envelope): mixed
    {
        /** @var ?TransportMessageIdStamp $stamp */
        $stamp = $envelope->last(TransportMessageIdStamp::class);

        if (null === $stamp || null === $stamp->getId()) {
            throw new RuntimeException('Transport does not define id for envelope');
        }

        return $stamp->getId();
    }
}
