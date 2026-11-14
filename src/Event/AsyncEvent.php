<?php

declare(strict_types=1);

namespace Flow\Event;

use Closure;
use Flow\Ip;
use Flow\JobInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Throwable;

/**
 * @template T1
 */
final class AsyncEvent extends Event
{
    private ?Closure $failure = null;

    private ?Closure $release = null;

    private bool $settled = false;

    /**
     * @param Closure|JobInterface<mixed,mixed> $job
     * @param Ip<T1>                            $ip
     */
    public function __construct(
        private Closure $async,
        private Closure $defer,
        private Closure|JobInterface $job,
        private Ip $ip,
        private Closure $callback
    ) {}

    public function getAsync(): Closure
    {
        return $this->async;
    }

    public function getDefer(): Closure
    {
        return $this->defer;
    }

    /**
     * @return Closure|JobInterface<mixed,mixed>
     */
    public function getJob(): Closure|JobInterface
    {
        return $this->job;
    }

    /**
     * @return Ip<T1>
     */
    public function getIp(): Ip
    {
        return $this->ip;
    }

    public function getCallback(): Closure
    {
        return $this->callback;
    }

    public function onFailure(Closure $failure): void
    {
        $this->failure = $failure;
    }

    public function execute(Closure $continuation, Closure $release): void
    {
        $this->release = $release;

        try {
            $continuation();
        } catch (Throwable $exception) {
            $this->fail($exception);
        }
    }

    public function settle(): bool
    {
        if ($this->settled) {
            return false;
        }

        $this->settled = true;
        $this->release();

        return true;
    }

    public function fail(Throwable $exception): void
    {
        $settled = $this->settled;
        $this->settled = true;
        $this->stopPropagation();
        $this->release();

        if ($this->failure !== null) {
            ($this->failure)($exception, $settled);

            return;
        }

        throw $exception;
    }

    private function release(): void
    {
        $release = $this->release;
        $this->release = null;

        if ($release !== null) {
            $release();
        }
    }
}
