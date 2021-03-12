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
    private int $railIndex; // internal rail index for supervisor

    public function __construct(
        private object $data // Information Packet data representing any object
    ) {
        $this->id = self::$ipId++;
        $this->railIndex = 0;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getCurrentRail(): int {
        return $this->railIndex;
    }

    public function nextRail(): void {
        $this->railIndex++;
    }

    public function getData(): object {
        return $this->data;
    }
}