<?php

declare(strict_types=1);

namespace Flow\Examples;

class Data
{
    public function __construct(public int $id, public ?int $number)
    {
    }
}
