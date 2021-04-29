<?php

declare(strict_types=1);

namespace RFBP\Transport\Receiver;

use RFBP\EnvelopeTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class CollectionReceiver implements ReceiverInterface
{
    use EnvelopeTrait;

    /**
     * @var array<mixed, ReceiverInterface>
     */
    private array $envelopePool;

    /**
     * @param iterable<ReceiverInterface> $receivers
     */
    public function __construct(private iterable $receivers)
    {
    }

    public function get(): iterable
    {
        foreach ($this->receivers as $receiver) {
            foreach ($receiver->get() as $envelope) {
                $id = $this->getEnvelopeId($envelope);
                $this->envelopePool[$id] = $receiver;
                yield $envelope;
            }
        }
    }

    public function ack(Envelope $envelope): void
    {
        $id = $this->getEnvelopeId($envelope);
        $this->envelopePool[$id]->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $id = $this->getEnvelopeId($envelope);
        $this->envelopePool[$id]->reject($envelope);
    }
}
