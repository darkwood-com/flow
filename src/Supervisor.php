<?php

namespace RFBP;

use Amp\Loop;
use RFBP\Stamp\FromTransportIdStamp;
use Symfony\Component\Messenger\Envelope as IP;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class Supervisor
{
    /** @var array<IP> */
    private array $ips;

    public function __construct(
        private ReceiverInterface $producer,
        private SenderInterface $consumer,
        /** @var array<Rail> */
        private $rails,
        private ?Rail $errorRail = null
    ) {
        $this->ips = [];

        foreach ($this->rails as )
    }

    public function start(): void {
        Loop::repeat(1, callback: function() {
            $ips = $this->producer->get();
            foreach ($ips as $ip) {
                $id = $this->getIpId($ip);
                if(!isset($this->ips[$id])) {
                    $this->ips[$id] = $ip;
                }
            }

            foreach ($this->ips as $ip) {
                /** @var IP $ip */
                $ip = $ip->getMessage();
                if($ip->getException()) {
                    $ip->setRailIndex(count($this->rails));
                    if($this->errorRail) {
                        ($this->errorRail)($ip);
                    }
                } elseif($ip->getRailIndex() < count($this->rails)) {
                    $this->rails[$ip->getRailIndex()]($ip);
                } else {
                    unset($this->ips[$ip->getId()]);
                    $this->producer->ack($ip);
                    $this->consumer->send(IP::wrap($ip, [$ip->last(FromTransportIdStamp::class)]));
                }
            }
        });
        Loop::run();
    }

    private function getIpId(IP $ip)
    {
        /** @var TransportMessageIdStamp $stamp */
        $stamp = $ip->last(TransportMessageIdStamp::class);

        return null !== $stamp ? $stamp->getId() : null;
    }
}