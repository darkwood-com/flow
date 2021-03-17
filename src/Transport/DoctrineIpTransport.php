<?php

namespace RFBP\Transport;

use Doctrine\DBAL\Connection as DbalConnection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineReceiver;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineSender;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;

class DoctrineIpTransport implements TransportInterface
{
    private ?SerializerInterface $serializer;

    public function __construct(
        private DbalConnection $connection,
        private ?string $id = 'supervisor',
        ?SerializerInterface $serializer = null
    )
    {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function get(): iterable
    {
        return $this->getReceiver()->get();
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        $this->getReceiver()->ack($envelope);
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        $this->getReceiver()->reject($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        if($this->id !== 'supervisor') {
            $envelope = $envelope->with(new FromTransportIdStamp($this->id));
        }
        return $this->getSender($envelope)->send($envelope);
    }

    private function queue($queue = 'default'): array {
        return Connection::buildConfiguration('doctrine://default?queue_name='.$queue);
    }

    private function getReceiver(): DoctrineReceiver
    {
        $connection = new Connection($this->queue($this->id), $this->connection);
        return new DoctrineReceiver($connection, $this->serializer);
    }

    private function getSender(Envelope $envelope): DoctrineSender
    {
        if($this->id === 'supervisor') {
            $id = $envelope->last(FromTransportIdStamp::class)->getId();
            $connection = new Connection($this->queue($id), $this->connection);
        } else {
            $connection = new Connection($this->queue('supervisor'), $this->connection);
        }

        return new DoctrineSender($connection, $this->serializer);
    }
}