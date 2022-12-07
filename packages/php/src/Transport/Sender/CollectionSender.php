<?php

declare(strict_types=1);

namespace RFBP\Transport\Sender;

use RFBP\EnvelopeTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class CollectionSender implements SenderInterface
{
    use EnvelopeTrait;

    /**
     * @param iterable<SenderInterface> $senders
     */
    public function __construct(private iterable $senders)
    {
    }

    public function send(Envelope $envelope): Envelope
    {
        foreach ($this->senders as $sender) {
            $sender->send($envelope);
        }

        return $envelope;
    }
}
