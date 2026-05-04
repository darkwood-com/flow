<?php

declare(strict_types=1);

namespace Flow\Examples\Model;

class ChunkData
{
    public function __construct(
        public int $id,
        public string $content,
        public bool $isFirst = false,
        public bool $isLast = false,
        public mixed $additionalRequests = null
    ) {}
}
