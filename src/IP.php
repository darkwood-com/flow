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
    private string $id; // internal IP unique identifier

    private string $state; // internal state for supervisor : 'process'|'error'|'done'
    private int $railIndex; // internal state for supervisor

    public function __construct(
        private object $data // Information Packet data representing any object
    ) {
        $this->id = uniqid('ip_', true);

        $this->state = 'process';
        $this->railIndex = 0;
    }

    public function getId(): string {
        return $this->id;
    }

    public function getCurrentRail(): int {
        return $this->railIndex;
    }

    public function nextRail(): void {
        $this->railIndex++;
    }

    public function errorRail(): void {
        $this->state = 'error';
    }

    public function done(): void {
        $this->state = 'done';
    }

    public function getData(): object {
        return $this->data;
    }
}