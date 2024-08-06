<?php

declare(strict_types=1);

namespace Flow\Event;

use Closure;
use Symfony\Contracts\EventDispatcher\Event;

final class AsyncEvent extends Event
{
    /**
     * @var array<mixed>
     */
    private array $args;

    /**
     * @param array<mixed> $args
     */
    public function __construct(private Closure $async, ...$args)
    {
        $this->args = $args;
    }

    public function getAsync(): Closure
    {
        return $this->async;
    }

    /**
     * @return array<mixed>
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}
