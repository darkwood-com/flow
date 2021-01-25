<?php

namespace RFBP;

class Pipe
{
    static int $pipeId = 0;
    private $id;
    private $ipJobs = [];

    public function __construct(private \Closure $job, private int $scale) {
        $this->id = self::$pipeId++;
    }

    public function run(&$ip) {
        if(!isset($this->ipJobs[$ip['id']])) {
            $this->ipJobs[$ip['id']] = null;
        }

        if (count($this->ipJobs) <= $this->scale) {
            $job = $this->job;
            $this->ipJobs[$ip['id']] = $job($ip['struct']);
        }

        print_r([
            'pipe_id' => $this->id,
            $this->ipJobs,
        ]);
        $job = $this->ipJobs[$ip['id']];
        if($job instanceof \Generator) {
            $job->next();

            if($job->valid() === false) {
                $ip['struct'] = $job->getReturn();
                unset($this->ipJobs[$ip['id']]);
                return true;
            }
        }

        return false;
    }
}