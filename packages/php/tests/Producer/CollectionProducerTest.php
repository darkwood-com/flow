<?php

declare(strict_types=1);

namespace RFBP\Test\Producer;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use RFBP\Producer\CollectionProducer;
use RFBP\Stamp\IpIdStampTrait;
use SplObjectStorage;
use stdClass;
use Symfony\Component\Messenger\Envelope as Ip;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IpIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface as ProducerInterface;

class CollectionProducerTest extends TestCase
{
    use IpIdStampTrait;

    /**
     * @param array<ProducerInterface> $producers
     * @param SplObjectStorage<ProducerInterface, array<Ip>> $expectedProducersIps
     * @dataProvider producerProvider
     */
    public function testGet($producers, $expectedProducersIps): void
    {
        $collectionProducer = new CollectionProducer($producers);
        $ips = $collectionProducer->get();

        $expectedIpIds = [];
        foreach ($expectedProducersIps as $producer) {
            $expectedIps = $expectedProducersIps[$producer];
            foreach ($expectedIps as $ip) {
                $expectedIpIds[] = $this->getIpId($ip);
            }
        }
        $ipIds = array_map(function (Ip $ip) {
            return $this->getIpId($ip);
        }, iterator_to_array($ips));

        $this->assertArraySimilar($expectedIpIds, $ipIds);
    }

    /**
     * @param array<ProducerInterface> $producers
     * @param SplObjectStorage<ProducerInterface, array<Ip>> $expectedProducersIps
     * @param array<mixed, ProducerInterface> $expectedAckIpsProducers
     * @param array<mixed, ProducerInterface> $expectedRejectIpsProducers
     * @dataProvider producerProvider
     */
    public function testAckAndReject($producers, $expectedProducersIps, $expectedAckIpsProducers, $expectedRejectIpsProducers): void
    {
        $collectionProducer = new CollectionProducer($producers);

        $ips = $collectionProducer->get();
        foreach ($ips as $ip) {
            $ipId = $this->getIpId($ip);
            $expectedProducer = null;
            foreach ($expectedProducersIps as $producer) {
                $expectedIps = $expectedProducersIps[$producer];
                foreach ($expectedIps as $expectedIp) {
                    $expectedIpId = $this->getIpId($expectedIp);
                    if ($ipId === $expectedIpId) {
                        $expectedProducer = $producer;
                        break 2;
                    }
                }
            }
            self::assertNotNull($expectedProducer);

            if (1 === random_int(0, 1)) {
                $collectionProducer->ack($ip);
                self::assertArrayHasKey($ipId, $expectedAckIpsProducers);
                self::assertSame($expectedProducer, $expectedAckIpsProducers[$ipId]);
            } else {
                $collectionProducer->reject($ip);
                self::assertArrayHasKey($ipId, $expectedRejectIpsProducers);
                self::assertSame($expectedProducer, $expectedRejectIpsProducers[$ipId]);
            }
        }
    }

    /**
     * @return array<array>
     */
    public function producerProvider(): array
    {
        $expectedProducersIps = new SplObjectStorage();
        /** @var array<mixed, ProducerInterface> $expectedAckIpsProducers */
        $expectedAckIpsProducers = new ArrayObject();
        /** @var array<mixed, ProducerInterface> $expectedRejectIpsProducers */
        $expectedRejectIpsProducers = new ArrayObject();
        $producers = [];
        for ($i = 0; $i < 10; ++$i) {
            $ips = [];
            for ($j = 0; $j < 3; ++$j) {
                $ips[] = Ip::wrap(new stdClass(), [new IpIdStamp(uniqid('ip_', true))]);
            }

            $producer = new class($ips, $expectedAckIpsProducers, $expectedRejectIpsProducers) implements ProducerInterface {
                use IpIdStampTrait;

                /**
                 * @param array<Ip> $ips
                 * @param array<mixed, ProducerInterface> $expectedAckIpsProducers
                 * @param array<mixed, ProducerInterface> $expectedRejectIpsProducers
                 */
                public function __construct(private $ips, private $expectedAckIpsProducers, private $expectedRejectIpsProducers)
                {
                }

                public function get(): iterable
                {
                    foreach ($this->ips as $ip) {
                        yield $ip;
                    }
                }

                public function ack(Ip $ip): void
                {
                    $id = $this->getIpId($ip);
                    $this->expectedAckIpsProducers[$id] = $this;
                }

                public function reject(Ip $ip): void
                {
                    $id = $this->getIpId($ip);
                    $this->expectedRejectIpsProducers[$id] = $this;
                }
            };
            $expectedProducersIps->offsetSet($producer, $ips);
            $producers[] = $producer;
        }

        return [
            [$producers, $expectedProducersIps, $expectedAckIpsProducers, $expectedRejectIpsProducers],
        ];
    }

    /**
     * Asserts that two associative arrays are similar.
     *
     * Both arrays must have the same indexes with identical values
     * without respect to key ordering
     * @param array<mixed, mixed> $expected
     * @param array<mixed, mixed> $array
     */
    protected function assertArraySimilar(array $expected, array $array): void
    {
        self::assertEquals([], array_diff_key($array, $expected));

        foreach ($expected as $key => $value) {
            if (is_array($value)) {
                self::assertArraySimilar($value, $array[$key]);
            } else {
                self::assertContains($value, $array);
            }
        }
    }
}
