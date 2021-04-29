<?php

declare(strict_types=1);

namespace RFBP;

interface DriverInterface
{
    public function coroutine(callable $callback, ?callable $onResolved): callable;

    public function tick(int $interval, callable $callback): void;

    public function run(): void;

    public function stop(): void;
}
