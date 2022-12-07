<?php

declare(strict_types=1);

namespace RFBP\Examples\Transport;

use Doctrine\DBAL\Connection as DbalConnection;
use RFBP\Examples\Stamp\DoctrineIpTransportIdStamp;
use RuntimeException;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineReceiver;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineSender;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class DoctrineIpTransport implements TransportInterface
{
    private ?SerializerInterface $serializer;
    private ?DoctrineReceiver $receiver;
    /** @var array<mixed, DoctrineSender> */
    private array $senders;

    public function __construct(
        private DbalConnection $connection,
        private ?string $id = 'supervisor',
        ?SerializerInterface $serializer = null
    ) {
        $this->serializer = $serializer ?? new PhpSerializer();
        $this->senders = [];
    }

    /**
     * {@inheritdoc}
     */
    public function get(): iterable
    {
        return ($this->receiver ?? $this->getReceiver())->get();
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        ($this->receiver ?? $this->getReceiver())->ack($envelope);
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        ($this->receiver ?? $this->getReceiver())->reject($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        if ('supervisor' !== $this->id) {
            $envelope = $envelope->with(new DoctrineIpTransportIdStamp($this->id));
        }

        return $this->getSender($envelope)->send($envelope);
    }

    /**
     * @return array<string, string>
     */
    private function queue(string $queue = 'default'): array
    {
        return Connection::buildConfiguration('doctrine://default?queue_name='.$queue);
    }

    private function getReceiver(): DoctrineReceiver
    {
        $connection = new Connection($this->queue($this->id), $this->connection);
        $this->receiver = new DoctrineReceiver($connection, $this->serializer);

        return $this->receiver;
    }

    private function getSender(Envelope $envelope): DoctrineSender
    {
        if ('supervisor' === $this->id) {
            $stamp = $envelope->last(DoctrineIpTransportIdStamp::class);
            if (!$stamp instanceof DoctrineIpTransportIdStamp) {
                throw new RuntimeException('Sender not found');
            }

            $queue = $stamp->getId();
        } else {
            $queue = 'supervisor';
        }

        if (!isset($this->senders[$queue])) {
            $connection = new Connection($this->queue($queue), $this->connection);
            $this->senders[$queue] = new DoctrineSender($connection, $this->serializer);
        }

        return $this->senders[$queue];
    }
}
