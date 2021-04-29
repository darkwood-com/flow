<?php

declare(strict_types=1);

namespace RFBP;

final class Ip
{
    public function __construct(private ?object $data = null)
    {
    }

    public function getData(): object
    {
        return $this->data;
    }
}
