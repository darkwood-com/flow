<?php

namespace RFBP;

use Amp\Loop;
use RFBP\Stamp\FromTransportIdStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class Supervisor
{
    /** @var array<Envelope> */
    private array $envelopes;

    public function __construct(
        private ReceiverInterface $producer,
        private SenderInterface $consumer,
        /** @var array<Rail> */
        private $rails,
        private ?Rail $errorRail = null
    ) {
        $this->envelopes = [];
    }

    public function start() {
        Loop::repeat(1, callback: function() {
            $envelopes = $this->producer->get();
            foreach ($envelopes as $envelope) {
                $ip = $envelope->getMessage();
                if(!isset($this->envelopes[$ip->getId()])) {
                    $this->envelopes[$ip->getId()] = $envelope;
                }
            }

            foreach ($this->envelopes as $envelope) {
                /** @var IP $ip */
                $ip = $envelope->getMessage();
                if($ip->getException()) {
                    $ip->setRailIndex(count($this->rails));
                    if($this->errorRail) {
                        ($this->errorRail)($ip);
                    }
                } elseif($ip->getRailIndex() < count($this->rails)) {
                    $this->rails[$ip->getRailIndex()]($ip);
                } else {
                    unset($this->envelopes[$ip->getId()]);
                    $this->producer->ack($envelope);
                    $this->consumer->send(Envelope::wrap($ip, [$envelope->last(FromTransportIdStamp::class)]));
                }
            }
        });
        Loop::run();
    }
}