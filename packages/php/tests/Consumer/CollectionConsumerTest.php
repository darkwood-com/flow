<?php

declare(strict_types=1);

namespace RFBP\Test\Consumer;

use PHPUnit\Framework\TestCase;
use RFBP\Consumer\CollectionConsumer;
use stdClass;
use ArrayObject;
use Symfony\Component\Messenger\Envelope as Ip;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface as ConsumerInterface;

class CollectionConsumerTest extends TestCase
{
    public function testSend(): void
    {
        $counter = new ArrayObject(['send' => 0]);
        $consumers = array_map(function () use ($counter) {
            $consumer = new class($counter) implements ConsumerInterface {
                /**
                 * @param ArrayObject<string, int> $counter
                 */
                public function __construct(private ArrayObject $counter)
                {
                }

                public function send(Ip $ip): Ip
                {
                    ++$this->counter['send'];

                    return $ip;
                }
            };

            return $consumer;
        }, array_fill(0, 10, 0));
        $collectionConsumer = new CollectionConsumer($consumers);
        $collectionConsumer->send(new Ip(new stdClass()));

        $this->assertEquals(10, $counter['send']);
    }
}
