<?php

declare(strict_types=1);

namespace Flow\Examples\Transport\Receiver;

use SplObjectStorage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class CollectionReceiver implements ReceiverInterface
{
    /**
     * @var SplObjectStorage<Envelope, ReceiverInterface>
     */
    private SplObjectStorage $envelopePool;

    /**
     * @param iterable<ReceiverInterface> $receivers
     */
    public function __construct(private iterable $receivers)
    {
        $this->envelopePool = new SplObjectStorage();
    }

    public function get(): iterable
    {
        foreach ($this->receivers as $receiver) {
            foreach ($receiver->get() as $envelope) {
                $this->envelopePool[$envelope] = $receiver;
                yield $envelope;
            }
        }
    }

    public function ack(Envelope $envelope): void
    {
        $this->envelopePool[$envelope]->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->envelopePool[$envelope]->reject($envelope);
    }
}
