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

        foreach ($rails as $index => $rail) {
            $rail->pipe($this->next($index + 1));
        }
        $this->errorRail->pipe($this->next());
    }

    private function next(?int $index = null): callable {
        return function(IP $ip, \Throwable $exception = null) use ($index) {
            $id = $this->getIpId($ip);

            if($exception) {
                $this->ipPool[$id][2] = $exception;
            } elseif (!is_null($index) && $index < count($this->rails)) {
                $this->ipPool[$id][1] = $index;
            } else {
                unset($this->ipPool[$id]);
                $this->producer->ack($ip);
                $this->consumer->send($ip);
            }
        };
    }

    public function start(): void
    {
        Loop::repeat(1, callback: function() {
            // producer receive new incoming IP and initialise their state
            $ips = $this->producer->get();
            foreach ($ips as $ip) {
                $id = $this->getIpId($ip);
                if(!isset($this->ipPool[$id])) {
                    $this->ipPool[$id] = [$ip, 0, null];
                }
            }

            // process IPs from the pool to their respective rail
            foreach ($this->ipPool as $state) {
                [$ip, $index, $exception] = $state;

                if($exception) {
                    ($this->errorRail)($ip, $exception);
                } else {
                    $this->rails[$index]($ip);
                }
            }
        });
        Loop::run();
    }

    private function getIpId(IP $ip): mixed
    {
        /** @var ?IPidStamp $stamp */
        $stamp = $ip->last(IPidStamp::class);

        if(is_null($stamp) || is_null($stamp->getId())) {
            throw new \RuntimeException('Transport does not define Id for IP');
        }

        return $stamp->getId();
    }
}