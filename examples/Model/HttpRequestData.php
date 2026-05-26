<?php

declare(strict_types=1);

namespace Flow\Examples\Model;

class HttpRequestData
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public int $id,
        public string $url,
        public array $options = [],
        public ?string $method = 'GET'
    ) {}
}
