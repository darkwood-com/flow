<?php

declare(strict_types=1);

namespace Flow\Test\Examples\Transport\Receiver;

use ArrayObject;
use Flow\Examples\Transport\Receiver\CollectionReceiver;
use PHPUnit\Framework\Attributes\DataProvider;
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
     */
    #[DataProvider('provideGetCases')]
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
     * @return array<array<mixed>>
     */
    public static function provideGetCases(): iterable
    {
        [$receivers, $expectedReceiversIps] = self::buildReceiverFixtures();

        return [[$receivers, $expectedReceiversIps]];
    }

    /**
     * @param array<ReceiverInterface>                             $receivers
     * @param SplObjectStorage<ReceiverInterface, array<Envelope>> $expectedReceiversIps
     * @param array<mixed, ReceiverInterface>                      $expectedAckIpsReceivers
     * @param array<mixed, ReceiverInterface>                      $expectedRejectIpsReceivers
     */
    #[DataProvider('provideAckAndRejectCases')]
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
    public static function provideAckAndRejectCases(): iterable
    {
        return [self::buildReceiverFixtures()];
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

    /**
     * @return array{0: array<ReceiverInterface>, 1: SplObjectStorage<ReceiverInterface, array<Envelope>>, 2: ArrayObject<string, ReceiverInterface>, 3: ArrayObject<string, ReceiverInterface>}
     */
    private static function buildReceiverFixtures(): array
    {
        $expectedReceiversIps = new SplObjectStorage();
        $expectedAckIpsReceivers = new ArrayObject();
        $expectedRejectIpsReceivers = new ArrayObject();
        $receivers = [];
        for ($i = 0; $i < 10; $i++) {
            $envelopes = [];
            for ($j = 0; $j < 3; $j++) {
                $envelopes[] = Envelope::wrap(new stdClass(), [new TransportMessageIdStamp(uniqid('envelope_', true))]);
            }

            $receiver = new class($envelopes, $expectedAckIpsReceivers, $expectedRejectIpsReceivers) implements ReceiverInterface {
                /**
                 * @param array<Envelope>                        $envelopes
                 * @param ArrayObject<string, ReceiverInterface> $expectedAckIpsReceivers
                 * @param ArrayObject<string, ReceiverInterface> $expectedRejectIpsReceivers
                 */
                public function __construct(
                    private array $envelopes,
                    private ArrayObject $expectedAckIpsReceivers,
                    private ArrayObject $expectedRejectIpsReceivers,
                ) {}

                public function get(): iterable
                {
                    foreach ($this->envelopes as $envelope) {
                        yield $envelope;
                    }
                }

                /**
                 * @return ArrayObject<string, ReceiverInterface>
                 */
                public function getExpectedAckIpsReceivers(): ArrayObject
                {
                    return $this->expectedAckIpsReceivers;
                }

                /**
                 * @return ArrayObject<string, ReceiverInterface>
                 */
                public function getExpectedRejectIpsReceivers(): ArrayObject
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

        return [$receivers, $expectedReceiversIps, $expectedAckIpsReceivers, $expectedRejectIpsReceivers];
    }

    private function getTransportMessageId(Envelope $envelope): string
    {
        /** @var null|TransportMessageIdStamp $stamp */
        $stamp = $envelope->last(TransportMessageIdStamp::class);

        return $stamp ? $stamp->getId() : '';
    }
}
