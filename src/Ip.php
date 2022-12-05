<?php

declare(strict_types=1);

namespace Flow;

final class Ip
{
    public function __construct(private ?object $data = null)
    {
    }

    public function getData(): ?object
    {
        return $this->data;
    }
}
