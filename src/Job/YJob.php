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
class YJob implements JobInterface
{
    /**
     * @param Closure(mixed): mixed|JobInterface<mixed, mixed> $job
     */
    public function __construct(private Closure|JobInterface $job) {}

    public function __invoke($data): mixed
    {
        $U = static fn (Closure $f) => $f($f);
        $Y = static fn (Closure|JobInterface $f) => $U(static fn (Closure $x) => $f(static fn ($y) => $U($x)($y)));
        $job = $this->job;

        return $Y($job)($data);
    }
}
