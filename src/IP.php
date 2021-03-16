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

    private int $railIndex = 0; // internal state for supervisor
    private ?\Throwable $exception = null;

    public function __construct(
        private object $data // Information Packet data representing any object
    ) {
        $this->id = uniqid('ip_', true);
    }

    public function getId(): string {
        return $this->id;
    }

    public function getData(): object {
        return $this->data;
    }

    public function getCurrentRail(): int {
        return $this->railIndex;
    }

    public function nextRail(): void {
        $this->railIndex++;
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }

    public function setException(?\Throwable $exception): void
    {
        $this->exception = $exception;
    }
}