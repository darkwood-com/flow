<?php

declare(strict_types=1);

namespace Flow\Test\Examples\Transport\Sender;

use ArrayObject;
use Flow\Examples\Transport\Sender\CollectionSender;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class CollectionSenderTest extends TestCase
{
    public function testSend(): void
    {
        $counter = new ArrayObject(['send' => 0]);
        $senders = array_map(function () use ($counter) {
            return new class($counter) implements SenderInterface {
                /**
                 * @param ArrayObject<string, int> $counter
                 */
                public function __construct(private ArrayObject $counter) {}

                public function send(Envelope $envelope): Envelope
                {
                    $this->counter['send']++;

                    return $envelope;
                }
            };
        }, array_fill(0, 10, 0));
        $collectionSender = new CollectionSender($senders);
        $collectionSender->send(new Envelope(new stdClass()));

        self::assertSame(10, $counter['send']);
    }
}
