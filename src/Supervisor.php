<?php

namespace RFBP;

use Amp\Loop;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RejectRedeliveredMessageException;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class Supervisor
{
    private MessageBusInterface $bus;

    /** @var array<IP> */
    protected $ips;

    public function __construct(
        private ReceiverInterface $producer,
        private SenderInterface $consumer,
        /** @var array<Rail> */
        private $rails,
        private ?Rail $error = null
    ) {
        /*$this->bus = new MessageBus([
            new HandleMessageMiddleware(),
            new SendMessageMiddleware()
        ]);**/
        $this->ips = [];
    }

    public function start() {
        Loop::run(function() {
            Loop::repeat(1, function() {
                $envelopes = $this->producer->get();
                foreach ($envelopes as $envelope) {
                    $this->ips[] = $envelope->getMessage();
                    $this->producer->ack($envelope);
                }

                foreach ($this->ips as $ip) {
                    if($ip->getCurrentRail() < count($this->rails)) {
                        $this->rails[$ip->getCurrentRail()]->run($ip);
                    } else {
                        $this->consumer->send(new Envelope($ip));
                        unset($this->ips[$ip->getId()]);
                    }
                }

                /*foreach ($envelopes as $envelope) {
                    $this->envelopes[] = $envelope;
                }

                foreach ($this->envelopes as $envelope) {
                    try {
                        $this->bus->dispatch($envelope->with(new ReceivedStamp('supervisor')));
                    } catch (\Throwable $throwable) {

                    }
                }*/

                //echo "******* Tick *******\n";
            });
        });
    }
}