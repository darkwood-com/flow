<?php

namespace RFBP;

use Amp\Loop;
use Symfony\Component\Messenger\Envelope as IP;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IPidStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class Supervisor
{
    /**
     * @var array<mixed, array>
     */
    private array $ipPool;

    public function __construct(
        private ReceiverInterface $producer,
        private SenderInterface $consumer,
        /** @var array<int, Rail> */
        private array $rails,
        private ?Rail $errorRail = null
    ) {
        $this->ipPool = [];

        foreach ($rails as $railIndex => $rail) {
            $rail->pipe($this->nextIpState($railIndex + 1));
        }
        if($this->errorRail) {
            $this->errorRail->pipe($this->nextIpState(count($this->rails)));
        }
    }

    private function nextIpState(int $railIndex): callable {
        return function(IP $ip, \Throwable $exception = null) use ($railIndex) {
            $id = $this->getId($ip);

            if($exception) {
                if($this->errorRail) {
                    $this->ipPool[$id][2] = $exception;
                } else {
                    unset($this->ipPool[$id]);
                    $this->producer->reject($ip);
                }
            } elseif ($railIndex < count($this->rails)) {
                $this->ipPool[$id] = [$ip, $railIndex, $exception];
            } else {
                unset($this->ipPool[$id]);
                $this->producer->ack($ip);
                $this->consumer->send($ip);
            }
        };
    }

    public function run(): void
    {
        Loop::repeat(1, function() {
            // producer receive new incoming IP and initialise their state
            $ips = $this->producer->get();
            foreach ($ips as $ip) {
                $id = $this->getId($ip);
                if(!isset($this->ipPool[$id])) {
                    $this->nextIpState(0)($ip);
                }
            }

            // process IPs from the pool to their respective rail
            foreach ($this->ipPool as $state) {
                [$ip, $railIndex, $exception] = $state;

                if($exception) {
                    ($this->errorRail)($ip, $exception);
                } else {
                    $this->rails[$railIndex]($ip);
                }
            }
        });
        Loop::run();
    }

    private function getId(IP $ip): mixed
    {
        /** @var ?IPidStamp $stamp */
        $stamp = $ip->last(IPidStamp::class);

        if(is_null($stamp) || is_null($stamp->getId())) {
            throw new \RuntimeException('Transport does not define Id for IP');
        }

        return $stamp->getId();
    }
}