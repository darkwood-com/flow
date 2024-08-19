<?php

declare(strict_types=1);

namespace Flow\Examples\Model;

readonly class DataA
{
    public function __construct(public int $id, public int $a, public int $b, public int $c) {}
}
