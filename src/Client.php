<?php

namespace RFBP;

class Client
{
    public function __construct(protected $producer) {

    }

    public function call($ip) {
        $this->producer->send($ip);
    }
}