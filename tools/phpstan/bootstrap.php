<?php

declare(strict_types=1);

/**
 * Bootstrap file to define OpenSwoole and TrueAsync stubs for PHPStan.
 */

namespace Async {

interface Completable {}

final class Coroutine implements Completable
{
    public function finally(\Closure $callback): void {}
}

final class FutureState
{
    public function complete(mixed $result): void {}

    public function error(\Throwable $throwable): void {}
}

final class Future implements Completable
{
    public function __construct(FutureState $state) {}

    /**
     * @template Tr
     *
     * @param callable(mixed):Tr $map
     *
     * @return Future<Tr>
     */
    public function map(callable $map): Future {}

    public function ignore(): Future {}

    public function await(?Completable $cancellation = null): mixed {}
}

function spawn(callable $task, mixed ...$args): Coroutine
{
    throw new \BadMethodCallException();
}

function await(Completable $awaitable, ?Completable $cancellation = null): mixed
{
    throw new \BadMethodCallException();
}

function delay(int $ms): void
{
    throw new \BadMethodCallException();
}

}

namespace {

// Define global co class if not already defined
// IMPORTANT: The class MUST be named 'co' (not 'bootstrap') because your code calls co::run() and co::sleep()
if (!class_exists('co', false)) {
    final class co
    {
        /**
         * Run a coroutine.
         *
         * @param callable $callback
         * @return mixed
         */
        public static function run(callable $callback) {}

        /**
         * Sleep for the specified number of seconds.
         *
         * @param int $seconds
         * @return bool
         */
        public static function sleep(int $seconds): bool {}
    }
}

}
