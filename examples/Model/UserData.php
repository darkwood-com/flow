<?php

declare(strict_types=1);

namespace Flow\Examples\Model;

class UserData
{
    /**
     * @param null|array<string, mixed> $todos
     * @param null|array<string, mixed> $posts
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?array $todos = null,
        public ?array $posts = null
    ) {}
}
