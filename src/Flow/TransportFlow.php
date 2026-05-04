<?php

declare(strict_types=1);

namespace Flow\Flow;

use Closure;
use Exception;
use Flow\Driver\FiberDriver;
use Flow\DriverInterface;
use Flow\FlowInterface;
use Flow\Ip;
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
        ?DriverInterface $driver = null
    ) {
        parent::__construct($flow);
        $this->fn(function (Envelope $envelope) {
            try {
                $this->consumer->send(Envelope::wrap($envelope->getMessage(), array_reduce($envelope->all(), static function (array $all, array $stamps) {
                    foreach ($stamps as $stamp) {
                        $all[] = $stamp;
                    }

                    return $all;
                }, [])));
                $this->producer->ack($envelope);
            } catch (Exception $e) {
                $this->producer->reject($envelope);
            }
        });

        $this->driver = $driver ?? new FiberDriver();
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
        $ip = new Ip($envelope);
        ($this->flow)($ip);
    }
}
