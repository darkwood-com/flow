<?php

declare(strict_types=1);

namespace Flow;

/**
 * @template-covariant T
 */
final class Ip
{
    /**
     * @param T $data
     */
    public function __construct(public readonly mixed $data = null) {}
}
