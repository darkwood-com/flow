<?php

namespace RFBP;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Worker;

class Client
{
    public function __construct(
        protected SenderInterface $sender,
        protected ReceiverInterface $receiver
    ) {}

    public function call(object $data) {
        $ip = new IP($data);
        $envelope = new Envelope($ip);
        $this->sender->send($envelope);
    }

    /**
     * @param callable $callback
     */
    public function wait($callback) {
        $bus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                IP::class => [$callback]
            ])),
        ]);
        $worker = new Worker(['redis' => $this->receiver], $bus);
        $worker->run();
    }
}