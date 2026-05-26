<?php

declare(strict_types=1);

namespace Flow;

/**
 * @template T1
 * @template T2
 */
interface JobInterface
{
    /**
     * @param T1 $data
     *
     * @return T2
     */
    public function __invoke($data): mixed;
}
