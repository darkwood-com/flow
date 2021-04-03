<?php

declare(strict_types=1);

namespace RFBP;

use Closure;
use RFBP\Driver\AmpDriver;
use RFBP\Driver\DriverInterface;
use RFBP\Ip\IpTrait;
use RFBP\Rail\Rail;
use Symfony\Component\Messenger\Envelope as Ip;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface as ProducerInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface as ConsumerInterface;
use Throwable;

class Supervisor
{
    use IpTrait;

    /**
     * @var array<mixed, Ip>
     */
    private array $ipPool;

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
        $this->ipPool = [];
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
                    $id = $this->getIpId($ip);
                    unset($this->ipPool[$id]);
                    $this->producer->reject($ip);
                }
            } elseif (null !== $index && $index < count($this->rails)) {
                ($this->rails[$index])($ip);
            } else {
                if ($this->consumer) {
                    $this->consumer->send($ip);
                }
                if ($this->producer) {
                    $this->producer->ack($ip);
                }
                $id = $this->getIpId($ip);
                unset($this->ipPool[$id]);
            }
        };
    }

    public function emit(Ip $ip, int $index = 0): void
    {
        $id = $this->getIpId($ip);
        if (!isset($this->ipPool[$id])) {
            $this->ipPool[$id] = $ip;
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
                $ips = $this->producer->get();
                foreach ($ips as $ip) {
                    $this->emit($ip);
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
