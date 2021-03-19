<?php

namespace RFBP;

use Symfony\Component\Messenger\Envelope as IP;
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

    public function call(object $data): void {
        $ip = new IP($data);
        $this->sender->send($ip);
    }

    public function wait(callable $callback): void {
        $bus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                IP::class => [$callback]
            ])),
        ]);
        $worker = new Worker(['transport' => $this->receiver], $bus);
        $worker->run();
    }
}