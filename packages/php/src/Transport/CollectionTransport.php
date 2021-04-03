<?php

declare(strict_types=1);

namespace RFBP\Producer;

use RFBP\Consumer\CollectionConsumer;
use Symfony\Component\Messenger\Envelope as Ip;
use Symfony\Component\Messenger\Transport\TransportInterface;

class CollectionTransport implements TransportInterface
{
    private CollectionProducer $collectionProducer;
    private CollectionConsumer $collectionConsumer;

    /**
     * @param iterable<TransportInterface> $transports
     */
    public function __construct(iterable $transports)
    {
        $this->collectionProducer = new CollectionProducer($transports);
        $this->collectionConsumer = new CollectionConsumer($transports);
    }

    public function get(): iterable
    {
        return $this->collectionProducer->get();
    }

    public function ack(Ip $ip): void
    {
        $this->collectionProducer->ack($ip);
    }

    public function reject(Ip $ip): void
    {
        $this->collectionProducer->reject($ip);
    }

    public function send(Ip $ip): Ip
    {
        return $this->collectionConsumer->send($ip);
    }
}
