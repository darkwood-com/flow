<?php

namespace RFBP;

use function Amp\coroutine;
use Amp\Promise;
use Symfony\Component\Messenger\Envelope as IP;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IPidStamp;

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
        /** @var ?IPidStamp $stamp */
        $stamp = $ip->last(IPidStamp::class);

        if(is_null($stamp) || is_null($stamp->getId())) {
            throw new \RuntimeException('Transport does not define Id for IP');
        }

        return $stamp->getId();
    }
}