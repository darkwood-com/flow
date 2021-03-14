<?php

namespace RFBP;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Worker;

class Client
{
    private string $id; // internal Client unique identifier

    protected $ipIds = [];

    public function __construct(
        protected SenderInterface $sender,
        protected ReceiverInterface $receiver
    ) {
        $this->id = uniqid('client_', true);
    }

    public function getId(): string {
        return $this->id;
    }

    public function call(object $data) {
        $ip = new IP($data);
        $envelope = new Envelope($ip);
        $this->sender->send($envelope);
        $this->ipIds[] = $ip->getId();
    }

    /**
     * @param callable $callback
     */
    public function wait($callback) {
        /*$bus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                IP::class => [$callback]
            ])),
        ]);
        $worker = new Worker(['transport' => $this->receiver], $bus);
        $worker->run();*/

        while (true) {
            $envelopes = $this->receiver->get();
            foreach ($envelopes as $envelope) {
                /** @var IP $ip */
                $ip = $envelope->getMessage();
                $key = array_search($ip->getId(), $this->ipIds, true);
                if ($key !== false) {
                    unset($this->ipIds[$key]);
                    $callback($ip);
                    $this->receiver->ack($envelope);
                }
            }

            usleep(1000000);
        }
    }
}