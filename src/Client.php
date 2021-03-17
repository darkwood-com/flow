<?php

namespace RFBP;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Worker;

class Client
{
    public function __construct(
        private SenderInterface $sender,
        private ReceiverInterface $receiver
    ) {}

    public function call(object $data) {
        $ip = new IP($data);
        $envelope = new Envelope($ip);
        $this->sender->send($envelope);
    }

    public function wait(callable $callback) {
        $bus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                IP::class => [$callback]
            ])),
        ]);
        $worker = new Worker(['transport' => $this->receiver], $bus);
        $worker->run();
    }
}