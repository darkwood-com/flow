<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Exception;
use Flow\Driver\AmpDriver;
use Flow\DriverInterface;
use Flow\EnvelopeTrait;
use Flow\FlowInterface;
use Flow\Ip;
use SplObjectStorage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * @template T1
 * @template T2
 *
 * @extends FlowDecorator<T1,T2>
 */
class TransportFlow extends FlowDecorator
{
    use EnvelopeTrait;

    /**
     * @var SplObjectStorage<Ip<T1>, Envelope>
     */
    private SplObjectStorage $envelopePool;

    /**
     * @var array<int, Envelope>
     */
    private array $envelopeIds;

    /**
     * @var DriverInterface<T1,T2>
     */
    private DriverInterface $driver;

    /**
     * @param null|DriverInterface<T1,T2> $driver
     */
    public function __construct(
        private FlowInterface $flow,
        private ReceiverInterface $producer,
        private SenderInterface $consumer,
        DriverInterface $driver = null
    ) {
        parent::__construct($flow);

        $this->envelopePool = new SplObjectStorage();
        $this->driver = $driver ?? new AmpDriver();
    }

    /**
     * Producer receive new incoming Ip and initialise their state.
     *
     * @return Closure when called, this cleanup pull interval
     */
    public function pull(int $interval): Closure
    {
        return $this->driver->tick($interval, function () {
            $envelopes = $this->producer->get();
            foreach ($envelopes as $envelope) {
                $this->emit($envelope);
            }
        });
    }

    private function emit(Envelope $envelope): void
    {
        $id = $this->getEnvelopeId($envelope);
        if (!isset($this->envelopeIds[$id])) {
            $this->envelopeIds[$id] = $envelope;
            $ip = new Ip($envelope->getMessage());
            $this->envelopePool->offsetSet($ip, $envelope);

            try {
                $self = $this;
                ($this->flow)($ip, static function ($ip) use ($self) {
                    $envelope = $self->envelopePool->offsetGet($ip);
                    $self->consumer->send(Envelope::wrap($ip->data, array_reduce($envelope->all(), static function (array $all, array $stamps) {
                        foreach ($stamps as $stamp) {
                            $all[] = $stamp;
                        }

                        return $all;
                    }, [])));
                    $self->producer->ack($envelope);

                    $self->envelopePool->offsetUnset($ip);
                    $id = $self->getEnvelopeId($envelope);
                    unset($self->envelopeIds[$id]);
                });
            } catch (Exception $e) {
                $envelope = $this->envelopePool->offsetGet($ip);
                $this->producer->reject($envelope);
                $this->envelopePool->offsetUnset($ip);
                $id = $this->getEnvelopeId($envelope);
                unset($this->envelopeIds[$id]);
            }
        }
    }
}
