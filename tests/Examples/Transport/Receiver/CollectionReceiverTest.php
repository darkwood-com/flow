<?php

declare(strict_types=1);

namespace Flow\Test\Examples\Transport\Receiver;

use ArrayObject;
use Flow\Examples\Transport\Receiver\CollectionReceiver;
use PHPUnit\Framework\TestCase;
use SplObjectStorage;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

use function is_array;

class CollectionReceiverTest extends TestCase
{
    /**
     * @param array<ReceiverInterface>                             $receivers
     * @param SplObjectStorage<ReceiverInterface, array<Envelope>> $expectedReceiversIps
     *
     * @dataProvider receiverProvider
     */
    public function testGet($receivers, $expectedReceiversIps): void
    {
        $collectionReceiver = new CollectionReceiver($receivers);
        $envelopes = $collectionReceiver->get();

        $expectedIpIds = [];
        foreach ($expectedReceiversIps as $receiver) {
            $expectedIps = $expectedReceiversIps[$receiver];
            foreach ($expectedIps as $envelope) {
                $expectedIpIds[] = $this->getTransportMessageId($envelope);
            }
        }
        $envelopeIds = array_map(function (Envelope $envelope) {
            return $this->getTransportMessageId($envelope);
        }, iterator_to_array($envelopes));

        $this->assertArraySimilar($expectedIpIds, $envelopeIds);
    }

    /**
     * @param array<ReceiverInterface>                             $receivers
     * @param SplObjectStorage<ReceiverInterface, array<Envelope>> $expectedReceiversIps
     * @param array<mixed, ReceiverInterface>                      $expectedAckIpsReceivers
     * @param array<mixed, ReceiverInterface>                      $expectedRejectIpsReceivers
     *
     * @dataProvider receiverProvider
     */
    public function testAckAndReject($receivers, $expectedReceiversIps, $expectedAckIpsReceivers, $expectedRejectIpsReceivers): void
    {
        $collectionReceiver = new CollectionReceiver($receivers);

        $envelopes = $collectionReceiver->get();
        foreach ($envelopes as $envelope) {
            $envelopeId = $this->getTransportMessageId($envelope);
            $expectedReceiver = null;
            foreach ($expectedReceiversIps as $receiver) {
                $expectedIps = $expectedReceiversIps[$receiver];
                foreach ($expectedIps as $expectedIp) {
                    $expectedIpId = $this->getTransportMessageId($expectedIp);
                    if ($envelopeId === $expectedIpId) {
                        $expectedReceiver = $receiver;

                        break 2;
                    }
                }
            }
            self::assertNotNull($expectedReceiver);

            if (1 === random_int(0, 1)) {
                $collectionReceiver->ack($envelope);
                self::assertArrayHasKey($envelopeId, $expectedAckIpsReceivers);
                self::assertSame($expectedReceiver, $expectedAckIpsReceivers[$envelopeId]);
            } else {
                $collectionReceiver->reject($envelope);
                self::assertArrayHasKey($envelopeId, $expectedRejectIpsReceivers);
                self::assertSame($expectedReceiver, $expectedRejectIpsReceivers[$envelopeId]);
            }
        }
    }

    /**
     * @return array<array<mixed>>
     */
    public static function receiverProvider(): iterable
    {
        $expectedReceiversIps = new SplObjectStorage();
        /** @var array<mixed, ReceiverInterface> $expectedAckIpsReceivers */
        $expectedAckIpsReceivers = new ArrayObject();
        /** @var array<mixed, ReceiverInterface> $expectedRejectIpsReceivers */
        $expectedRejectIpsReceivers = new ArrayObject();
        $receivers = [];
        for ($i = 0; $i < 10; $i++) {
            $envelopes = [];
            for ($j = 0; $j < 3; $j++) {
                $envelopes[] = Envelope::wrap(new stdClass(), [new TransportMessageIdStamp(uniqid('envelope_', true))]);
            }

            $receiver = new class($envelopes, $expectedAckIpsReceivers, $expectedRejectIpsReceivers) implements ReceiverInterface {
                /**
                 * @param array<Envelope>                 $envelopes
                 * @param array<mixed, ReceiverInterface> $expectedAckIpsReceivers
                 * @param array<mixed, ReceiverInterface> $expectedRejectIpsReceivers
                 */
                public function __construct(private $envelopes, private $expectedAckIpsReceivers, private $expectedRejectIpsReceivers) {}

                public function get(): iterable
                {
                    foreach ($this->envelopes as $envelope) {
                        yield $envelope;
                    }
                }

                /**
                 * @return array<mixed, ReceiverInterface>
                 */
                public function getExpectedAckIpsReceivers()
                {
                    return $this->expectedAckIpsReceivers;
                }

                /**
                 * @return array<mixed, ReceiverInterface>
                 */
                public function getExpectedRejectIpsReceivers()
                {
                    return $this->expectedRejectIpsReceivers;
                }

                public function ack(Envelope $envelope): void
                {
                    $id = $this->getTransportMessageId($envelope);
                    $this->expectedAckIpsReceivers[$id] = $this;
                }

                public function reject(Envelope $envelope): void
                {
                    $id = $this->getTransportMessageId($envelope);
                    $this->expectedRejectIpsReceivers[$id] = $this;
                }

                private function getTransportMessageId(Envelope $envelope): string
                {
                    /** @var null|TransportMessageIdStamp $stamp */
                    $stamp = $envelope->last(TransportMessageIdStamp::class);

                    return $stamp ? $stamp->getId() : '';
                }
            };
            $expectedReceiversIps->offsetSet($receiver, $envelopes);
            $receivers[] = $receiver;
        }

        return [
            [$receivers, $expectedReceiversIps, $expectedAckIpsReceivers, $expectedRejectIpsReceivers],
        ];
    }

    /**
     * Asserts that two associative arrays are similar.
     *
     * Both arrays must have the same indexes with identical values
     * without respect to key ordering
     *
     * @param array<mixed, mixed> $expected
     * @param array<mixed, mixed> $array
     */
    protected function assertArraySimilar(array $expected, array $array): void
    {
        self::assertSame([], array_diff_key($array, $expected));

        foreach ($expected as $key => $value) {
            if (is_array($value)) {
                self::assertArraySimilar($value, $array[$key]);
            } else {
                self::assertContains($value, $array);
            }
        }
    }

    private function getTransportMessageId(Envelope $envelope): string
    {
        /** @var null|TransportMessageIdStamp $stamp */
        $stamp = $envelope->last(TransportMessageIdStamp::class);

        return $stamp ? $stamp->getId() : '';
    }
}
