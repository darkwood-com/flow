<?php

declare(strict_types=1);

namespace RFBP\Consumer;

use RFBP\Stamp\IpIdStampTrait;
use Symfony\Component\Messenger\Envelope as Ip;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface as ConsumerInterface;

class CollectionConsumer implements ConsumerInterface
{
    use IpIdStampTrait;

    /**
     * @param iterable<ConsumerInterface> $consumers
     */
    public function __construct(private iterable $consumers)
    {
    }

    public function send(Ip $ip): Ip
    {
        foreach ($this->consumers as $consumer) {
            $consumer->send($ip);
        }

        return $ip;
    }
}
