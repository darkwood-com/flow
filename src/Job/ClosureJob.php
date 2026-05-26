<?php

declare(strict_types=1);

namespace Flow\Job;

use Closure;
use Flow\JobInterface;

/**
 * @template TArgs
 * @template TReturn
 *
 * @implements JobInterface<TArgs,TReturn>
 */
class ClosureJob implements JobInterface
{
    /**
     * @param Closure(TArgs): TReturn|JobInterface<TArgs, TReturn> $job
     */
    public function __construct(private Closure|JobInterface $job) {}

    public function __invoke($data): mixed
    {
        $job = $this->job;

        return $job($data);
    }
}
