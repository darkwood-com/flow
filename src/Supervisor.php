<?php

namespace RFBP;

use Amp\Loop;
use RFBP\Transport\FromTransportIdStamp;
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
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class Supervisor
{
    private MessageBusInterface $bus;

    /** @var array<Envelope> */
    protected $envelopes;

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
        $this->envelopes = [];
    }

    public function start() {
        Loop::run(function() {
            Loop::repeat(1, function() {
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
                    if($ip->getCurrentRail() < count($this->rails)) {
                        $this->rails[$ip->getCurrentRail()]->run($ip);
                    } else {
                        unset($this->envelopes[$ip->getId()]);
                        $this->producer->ack($envelope);
                        $this->consumer->send(Envelope::wrap($ip, [$envelope->last(FromTransportIdStamp::class)]));
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