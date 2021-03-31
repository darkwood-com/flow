<?php

declare(strict_types=1);

namespace RFBP;

use Amp\Loop;
use Closure;
use RuntimeException;
use Symfony\Component\Messenger\Envelope as IP;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IPidStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Throwable;

class Supervisor
{
    /**
     * @var array<mixed, array>
     */
    private array $ipPool;

    public function __construct(
        private ReceiverInterface $producer,
        private SenderInterface $consumer,
        /** @var array<int, Rail>*/
        private array $rails,
        private ?Rail $errorRail = null
    ) {
        $this->ipPool = [];

        foreach ($rails as $index => $rail) {
            $rail->pipe($this->nextIpState($index + 1 < count($rails) ? $rails[$index + 1] : null));
        }
        if ($errorRail) {
            $errorRail->pipe($this->nextIpState());
        }
    }

    private function nextIpState(?Rail $rail = null): Closure
    {
        return function (IP $ip, Throwable $exception = null) use ($rail) {
            $id = $this->getId($ip);

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
                unset($this->ipPool[$id]);
                $this->producer->ack($ip);
                $this->consumer->send($ip);
            }
        };
    }

    public function run(): void
    {
        Loop::repeat(1, function () {
            // producer receive new incoming IP and initialise their state
            $ips = $this->producer->get();
            foreach ($ips as $ip) {
                $id = $this->getId($ip);
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
        Loop::run();
    }

    public function stop(): void
    {
        Loop::stop();
    }

    private function getId(IP $ip): mixed
    {
        /** @var ?IPidStamp $stamp */
        $stamp = $ip->last(IPidStamp::class);

        if (is_null($stamp) || is_null($stamp->getId())) {
            throw new RuntimeException('Transport does not define Id for IP');
        }

        return $stamp->getId();
    }
}
