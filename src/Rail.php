<?php

namespace RFBP;

use Amp\Coroutine;

class Rail
{
    private static int $railId = 0;

    private int $id; // internal rail unique identifier
    private $ipJobs = [];

    public function __construct(
        private \Closure $job,
        private int $scale
    ) {
        $this->id = self::$railId++;
    }

    public function run(IP $ip): void {
        // does the rail can scale ?
        if (count($this->ipJobs) >= $this->scale) {
            return;
        }

        // create an new job coroutine instance with IP data if not exist
        if(!isset($this->ipJobs[$ip->getId()])) {
            $job = $this->job;
            $this->ipJobs[$ip->getId()] = $job($ip->getData());
            $promise = new Coroutine($this->ipJobs[$ip->getId()]);
            $promise->onResolve(function() use ($ip) {
                $ip->nextRail();
                unset($this->ipJobs[$ip->getId()]);
            });
        }
    }
}