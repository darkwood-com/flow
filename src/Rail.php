<?php

namespace RFBP;

use function Amp\coroutine;

class Rail
{
    /**
     * @var array<string, bool>
     */
    private array $ipJobs;

    public function __construct(
        private \Closure $job,
        private ?int $scale = 1
    ) {
        $this->ipJobs = [];
    }

    public function __invoke(IP $ip): void {
        // does the rail can scale ?
        if (count($this->ipJobs) >= $this->scale) {
            return;
        }

        // create an new job coroutine instance with IP data if not exist
        if(!isset($this->ipJobs[$ip->getId()])) {
            $this->ipJobs[$ip->getId()] = true;

            $promise = coroutine($this->job)($ip->getData(), $ip->getException());
            $promise->onResolve(function(\Throwable $exception = null) use ($ip) {
                if($exception) {
                    $ip->setException($exception);
                } else {
                    $ip->setRailIndex($ip->getRailIndex() + 1);
                    $ip->setException(null);
                }

                unset($this->ipJobs[$ip->getId()]);
            });
        }
    }
}