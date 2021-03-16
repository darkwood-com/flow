<?php

namespace RFBP;

use Amp\Loop;
use RFBP\Transport\FromTransportIdStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class Supervisor
{
    /** @var array<Envelope> */
    protected $envelopes;

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
                if($this->errorRail && $ip->getException()) {
                    while($ip->getCurrentRail() < count($this->rails)) {
                        $ip->nextRail();
                    }
                    ($this->errorRail)($ip);
                } elseif($ip->getCurrentRail() < count($this->rails)) {
                    $this->rails[$ip->getCurrentRail()]($ip);
                } else {
                    unset($this->envelopes[$ip->getId()]);
                    $this->producer->ack($envelope);
                    $this->consumer->send(Envelope::wrap($ip, [$envelope->last(FromTransportIdStamp::class)]));
                }
            }

            //echo "******* Tick *******\n";
        });
        Loop::run();
    }
}