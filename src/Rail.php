<?php

namespace RFBP;

use function Amp\coroutine;
use Amp\Promise;
use Symfony\Component\Messenger\Envelope as IP;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class Rail
{
    /**
     * @var array<string, bool>
     */
    private array $ipJobs;
    private ?\Closure $pipeCallback = null;

    public function __construct(
        private \Closure $job,
        private ?int $scale = 1
    ) {
        $this->ipJobs = [];
    }

    public function __invoke(IP $ip, \Throwable $exception = null): void {
        // does the rail can scale ?
        if (count($this->ipJobs) >= $this->scale) {
            return;
        }

        // create an new job coroutine instance with IP data if not exist
        $id = $this->getIpId($ip);
        if(!isset($this->ipJobs[$id])) {
            $this->ipJobs[$id] = true;

            /** @var Promise $promise */
            $promise = coroutine($this->job)($ip->getMessage(), $exception);
            if($this->pipeCallback) {
                $promise->onResolve(function(\Throwable $exception = null) use ($ip) {
                    ($this->pipeCallback)($ip, $exception);
                });
            }
            $promise->onResolve(function() use ($id) {
                unset($this->ipJobs[$id]);
            });
        }
    }

    public function pipe(?\Closure $callback = null)
    {
        $this->pipeCallback = $callback;
    }

    private function getIpId(IP $ip)
    {
        /** @var TransportMessageIdStamp $stamp */
        $stamp = $ip->last(TransportMessageIdStamp::class);

        return null !== $stamp ? $stamp->getId() : null;
    }
}