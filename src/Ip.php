<?php

declare(strict_types=1);

namespace Flow;

/**
 * @template-covariant T
 */
final readonly class Ip
{
    /**
     * @param T $data
     */
    public function __construct(public mixed $data = null) {}
}
