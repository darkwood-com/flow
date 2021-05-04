<?php

declare(strict_types=1);

namespace RFBP\Rail;

use Closure;
use LogicException;
use RFBP\Driver\AmpDriver;
use RFBP\DriverInterface;
use RFBP\EnvelopeTrait;
use RFBP\Ip;
use RFBP\RailInterface;
use SplObjectStorage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Throwable;

class TransportRail implements RailInterface
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

    private ?Closure $pipeCallback = null;

    /**
     * @param array<string, mixed> $options valid options are:
     *                                      - interval (default: 0): tick interval in milliseconds
     */
    public function __construct(
        private RailInterface $rail,
        private ReceiverInterface $producer,
        private SenderInterface $consumer,
        ?DriverInterface $driver = null,
        array $options = []
    ) {
        $this->envelopePool = new SplObjectStorage();
        $this->driver = $driver ?? new AmpDriver();

        $options = array_merge([
            'interval' => 1,
        ], $options);

        // producer receive new incoming Ip and initialise their state
        $this->driver->tick($options['interval'], function () {
            $envelopes = $this->producer->get();
            foreach ($envelopes as $envelope) {
                $this->emit($envelope);
            }
        });

        $rail->pipe(function (Ip $ip, Throwable $exception = null) {
            if ($exception) {
                if ($this->envelopePool->contains($ip)) {
                    $envelope = $this->envelopePool->offsetGet($ip);
                    $this->producer->reject($envelope);
                    $this->envelopePool->offsetUnset($ip);
                    $id = $this->getEnvelopeId($envelope);
                    unset($this->envelopeIds[$id]);
                }

                if ($this->pipeCallback) {
                    ($this->pipeCallback)($ip, $exception);
                }
            } else {
                if ($this->envelopePool->contains($ip)) {
                    $envelope = $this->envelopePool->offsetGet($ip);
                    $this->consumer->send(Envelope::wrap($ip->getData(), array_reduce($envelope->all(), static function (array $all, array $stamps) {
                        foreach ($stamps as $stamp) {
                            $all[] = $stamp;
                        }

                        return $all;
                    }, [])));
                    $this->producer->ack($envelope);

                    $this->envelopePool->offsetUnset($ip);
                    $id = $this->getEnvelopeId($envelope);
                    unset($this->envelopeIds[$id]);
                }

                if ($this->pipeCallback) {
                    ($this->pipeCallback)($ip, $exception);
                }
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
            ($this->rail)($ip);
        }
    }

    public function __invoke(Ip $ip, mixed $context = null): void
    {
        ($this->rail)($ip);
    }

    public function pipe(Closure $callback): void
    {
        if ($this->pipeCallback) {
            throw new LogicException('Callback is already set');
        }

        $this->pipeCallback = $callback;
    }
}
