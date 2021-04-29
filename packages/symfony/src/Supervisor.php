<?php

declare(strict_types=1);

namespace RFBP;

use Closure;
use RFBP\Driver\AmpDriver;
use RFBP\Rail\Rail;
use SplObjectStorage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface as ProducerInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface as ConsumerInterface;
use Throwable;

class Supervisor
{
    use EnvelopeTrait;

    /**
     * @var SplObjectStorage<Ip, Envelope>
     */
    private SplObjectStorage $envelopePool;

    /**
     * @var array<int, Envelope>
     */
    private array $envelopeIds;

    private DriverInterface $driver;

    /**
     * @param array<int, Rail> $rails
     */
    public function __construct(
        private array $rails,
        private ?Rail $errorRail = null,
        private ?ProducerInterface $producer = null,
        private ?ConsumerInterface $consumer = null,
        ?DriverInterface $driver = null
    ) {
        $this->envelopePool = new SplObjectStorage();
        $this->driver = $driver ?? new AmpDriver();

        foreach ($rails as $index => $rail) {
            $rail->pipe($this->nextIpState($index + 1));
        }
        if ($errorRail) {
            $errorRail->pipe($this->nextIpState(null));
        }
    }

    private function nextIpState(?int $index): Closure
    {
        return function (Ip $ip, Throwable $exception = null) use ($index) {
            if ($exception) {
                if ($this->errorRail) {
                    ($this->errorRail)($ip, $exception);
                } else {
                    $envelope = $this->envelopePool->offsetGet($ip);
                    $this->producer->reject($envelope);
                    $this->envelopePool->offsetUnset($ip);
                    $id = $this->getEnvelopeId($envelope);
                    unset($this->envelopeIds[$id]);
                }
            } elseif (null !== $index && $index < count($this->rails)) {
                ($this->rails[$index])($ip);
            } else {
                $envelope = $this->envelopePool->offsetGet($ip);
                if ($this->consumer) {
                    $this->consumer->send(Envelope::wrap($ip->getData(), array_reduce($envelope->all(), static function (array $all, array $stamps) {
                        foreach ($stamps as $stamp) {
                            $all[] = $stamp;
                        }

                        return $all;
                    }, [])));
                }
                if ($this->producer) {
                    $this->producer->ack($envelope);
                }
                $this->envelopePool->offsetUnset($ip);
                $id = $this->getEnvelopeId($envelope);
                unset($this->envelopeIds[$id]);
            }
        };
    }

    private function emit(Envelope $envelope, int $index = 0): void
    {
        $id = $this->getEnvelopeId($envelope);
        if (!isset($this->envelopeIds[$id])) {
            $this->envelopeIds[$id] = $envelope;
            $ip = new Ip($envelope->getMessage());
            $this->envelopePool->offsetSet($ip, $envelope);
            $this->nextIpState($index)($ip);
        }
    }

    /**
     * @param array<string, mixed> $options valid options are:
     *                                      - interval (default: 0): tick interval in milliseconds
     */
    public function run(array $options = []): void
    {
        $options = array_merge([
            'interval' => 1,
        ], $options);

        if ($this->producer) {
            // producer receive new incoming Ip and initialise their state
            $this->driver->tick($options['interval'], function () {
                $envelopes = $this->producer->get();
                foreach ($envelopes as $envelope) {
                    $this->emit($envelope);
                }
            });
        }

        $this->driver->run();
    }

    public function stop(): void
    {
        $this->driver->stop();
    }
}
