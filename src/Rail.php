<?php

namespace RFBP;

use function Amp\coroutine;
use Amp\Promise;
use Symfony\Component\Messenger\Envelope as IP;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IPid;

class Rail
{
    /**
     * @var array<mixed, bool>
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

            /** @var Promise<void> $promise */
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

    public function pipe(?\Closure $callback = null): void
    {
        $this->pipeCallback = $callback;
    }

    private function getIpId(IP $ip): mixed
    {
        /** @var ?IPid $stamp */
        $stamp = $ip->last(IPid::class);

        if(is_null($stamp) || $stamp->getId() === null) {
            throw new \RuntimeException('Transport does not define Id for IP');
        }

        return $stamp->getId();
    }
}