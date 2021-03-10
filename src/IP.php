<?php

namespace RFBP;

/**
 * Information Packet
 *
 * Class IP
 * @package RFBP
 */
class IP
{
    private static int $ipId = 0;

    private int $id; // internal IP unique identifier
    private int $pipeIndex; // internal pipe index for supervisor

    public function __construct(
        private object $data // Information Packet data representing any object
    ) {
        $this->id = self::$ipId++;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getPipeIndex(): int {
        return $this->pipeIndex;
    }

    public function getData(): object {
        $this->data;
    }
}