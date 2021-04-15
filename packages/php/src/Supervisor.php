<?php

declare(strict_types=1);

namespace RFBP;

use Closure;
use RFBP\Driver\AmpDriver;
use RFBP\Driver\DriverInterface;
use RFBP\Ip\IpTrait;
use Symfony\Component\Messenger\Envelope as Ip;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface as ProducerInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface as ConsumerInterface;
use Throwable;

class Supervisor
{
    use IpTrait;

    /**
     * @var array<mixed, array>
     */
    private array $ipPool;

    private DriverInterface $driver;

    /**
     * @param array<int, Rail> $rails
     */
    public function __construct(
        private ProducerInterface $producer,
        private ConsumerInterface $consumer,
        private array $rails,
        private ?Rail $errorRail = null,
        ?DriverInterface $driver = null
    ) {
        $this->ipPool = [];
        $this->driver = $driver ?? new AmpDriver();

        foreach ($rails as $index => $rail) {
            $rail->pipe($this->nextIpState($index + 1 < count($rails) ? $rails[$index + 1] : null));
        }
        if ($errorRail) {
            $errorRail->pipe($this->nextIpState());
        }
    }

    private function nextIpState(?Rail $rail = null): Closure
    {
        return function (Ip $ip, Throwable $exception = null) use ($rail) {
            $id = $this->getIpId($ip);

            if ($exception) {
                if ($this->errorRail) {
                    $this->ipPool[$id] = [$this->errorRail, $ip, $exception];
                } else {
                    unset($this->ipPool[$id]);
                    $this->producer->reject($ip);
                }
            } elseif ($rail) {
                $this->ipPool[$id] = [$rail, $ip, null];
            } else {
                $this->consumer->send($ip);
                $this->producer->ack($ip);
                unset($this->ipPool[$id]);
            }
        };
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

        $this->driver->tick($options['interval'], function () {
            // producer receive new incoming Ip and initialise their state
            $ips = $this->producer->get();
            foreach ($ips as $ip) {
                $id = $this->getIpId($ip);
                if (!isset($this->ipPool[$id])) {
                    $this->nextIpState(count($this->rails) > 0 ? $this->rails[0] : null)($ip);
                }
            }

            // process IPs from the pool to their respective rail
            foreach ($this->ipPool as $state) {
                [$rail, $ip, $exception] = $state;
                ($rail)($ip, $exception);
            }
        });

        $this->driver->run();
    }

    public function stop(): void
    {
        $this->driver->stop();
    }
}
