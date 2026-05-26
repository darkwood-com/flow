<?php

declare(strict_types=1);

namespace Flow\Examples\Transport\Sender;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class CollectionSender implements SenderInterface
{
    /**
     * @param iterable<SenderInterface> $senders
     */
    public function __construct(private iterable $senders) {}

    public function send(Envelope $envelope): Envelope
    {
        foreach ($this->senders as $sender) {
            $sender->send($envelope);
        }

        return $envelope;
    }
}
