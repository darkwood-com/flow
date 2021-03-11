<?php

namespace RFBP;

use Amp\Coroutine;

class Pipe
{
    private static int $pipeId = 0;

    private int $id; // internal pipe unique identifier
    private $ipJobs = [];

    public function __construct(
        private \Closure $job,
        private int $scale
    ) {
        $this->id = self::$pipeId++;
    }

    public function run(IP $ip): void {
        // does the pipe can scale ?
        if (count($this->ipJobs) >= $this->scale) {
            return;
        }

        // create an new job instance with IP data if not exist
        if(!isset($this->ipJobs[$ip->getId()])) {
            $job = $this->job;
            $this->ipJobs[$ip->getId()] = $job($ip->getData());
            $promise = new Coroutine($this->ipJobs[$ip->getId()]);
            $promise->onResolve(function() use ($ip) {
                $ip->nextPipe();
                unset($this->ipJobs[$ip->getId()]);
            });
        }
    }
}