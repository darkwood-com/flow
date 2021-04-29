<?php

declare(strict_types=1);

namespace RFBP;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Worker;

class Client
{
    public function __construct(
        private SenderInterface $sender,
        private ReceiverInterface $receiver
    ) {
    }

    /**
     * @param ?int $delay The delay in milliseconds
     */
    public function call(object $data, ?int $delay = null): void
    {
        $ip = Envelope::wrap($data, $delay ? [new DelayStamp($delay)] : []);
        $this->sender->send($ip);
    }

    /**
     * @param HandlerDescriptor[][]|callable[][] $handlers
     */
    public function wait(array $handlers): void
    {
        $bus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator($handlers)),
        ]);
        $worker = new Worker(['transport' => $this->receiver], $bus);
        $worker->run();
    }
}
