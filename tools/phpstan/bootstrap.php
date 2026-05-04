<?php

declare(strict_types=1);

/**
 * Bootstrap file to define OpenSwoole classes for PHPStan.
 */

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
