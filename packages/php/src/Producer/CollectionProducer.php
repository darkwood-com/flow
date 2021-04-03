<?php

declare(strict_types=1);

namespace RFBP\Producer;

use RFBP\Ip\IpTrait;
use Symfony\Component\Messenger\Envelope as Ip;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface as ProducerInterface;

class CollectionProducer implements ProducerInterface
{
    use IpTrait;

    /**
     * @var array<mixed, ProducerInterface>
     */
    private array $ipPool;

    /**
     * @param iterable<ProducerInterface> $producers
     */
    public function __construct(private iterable $producers)
    {
    }

    public function get(): iterable
    {
        foreach ($this->producers as $producer) {
            foreach ($producer->get() as $ip) {
                $id = $this->getIpId($ip);
                $this->ipPool[$id] = $producer;
                yield $ip;
            }
        }
    }

    public function ack(Ip $ip): void
    {
        $id = $this->getIpId($ip);
        $this->ipPool[$id]->ack($ip);
    }

    public function reject(Ip $ip): void
    {
        $id = $this->getIpId($ip);
        $this->ipPool[$id]->reject($ip);
    }
}
