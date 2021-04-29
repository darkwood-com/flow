<?php

declare(strict_types=1);

namespace RFBP\Transport;

use RFBP\Transport\Receiver\CollectionReceiver;
use RFBP\Transport\Sender\CollectionSender;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

class CollectionTransport implements TransportInterface
{
    private CollectionReceiver $collectionReceiver;
    private CollectionSender $collectionSender;

    /**
     * @param iterable<TransportInterface> $transports
     */
    public function __construct(iterable $transports)
    {
        $this->collectionReceiver = new CollectionReceiver($transports);
        $this->collectionSender = new CollectionSender($transports);
    }

    public function get(): iterable
    {
        return $this->collectionReceiver->get();
    }

    public function ack(Envelope $envelope): void
    {
        $this->collectionReceiver->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->collectionReceiver->reject($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        return $this->collectionSender->send($envelope);
    }
}
