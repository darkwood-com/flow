<?php

declare(strict_types=1);

namespace RFBP\Test\Transport\Sender;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use RFBP\Transport\Sender\CollectionSender;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class CollectionSenderTest extends TestCase
{
    public function testSend(): void
    {
        $counter = new ArrayObject(['send' => 0]);
        $senders = array_map(function () use ($counter) {
            $sender = new class($counter) implements SenderInterface {
                /**
                 * @param ArrayObject<string, int> $counter
                 */
                public function __construct(private ArrayObject $counter)
                {
                }

                public function send(Envelope $envelope): Envelope
                {
                    ++$this->counter['send'];

                    return $envelope;
                }
            };

            return $sender;
        }, array_fill(0, 10, 0));
        $collectionSender = new CollectionSender($senders);
        $collectionSender->send(new Envelope(new stdClass()));

        self::assertEquals(10, $counter['send']);
    }
}
