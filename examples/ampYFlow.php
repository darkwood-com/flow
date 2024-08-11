<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Amp\DeferredFuture;
use Amp\Future;
use Revolt\EventLoop;

use function Amp\async;

// Define the Y-Combinator
$U = static fn (Closure $f) => $f($f);
$Y = static fn (Closure $f) => $U(static fn (Closure $x) => $f(static fn ($y) => $U($x)($y)));

function wrapWithDeferred(Closure $job): Future
{
    $deferred = new DeferredFuture();

    // Queue the operation to be executed in the event loop
    EventLoop::queue(static function () use ($job, $deferred) {
        $job(static function ($value) use ($deferred) {
            $deferred->complete($value);
        }, static function (Future $future, $next) {
            $future->map($next);
        });
    });

    return $deferred->getFuture();
}

/*function factorialGen(callable $func): Closure
{
    return static function (int $n) use ($func): int {
        return ($n <= 1) ? 1 : $n * $func($n - 1);
    };
}*/

// Define the async factorial function using the Y-Combinator
$asyncFactorial = $Y(static function ($factorial) {
    return static function ($n) use ($factorial): Future {
        return wrapWithDeferred(static function ($complete, $async) use ($n, $factorial) {
            if ($n <= 1) {
                $complete(1);
            } else {
                $async($factorial($n - 1), static function ($result) use ($n, $complete) {
                    $complete($n * $result);
                });
            }
        });
    };
});

// The main loop that runs the async task
$loop = static function () use ($asyncFactorial) {
    $future = $asyncFactorial(5);

    // Use the map method to handle the result when it's ready
    $future->map(static function ($result) {
        echo 'Factorial: ' . $result . PHP_EOL;
    });
};

// Defer the loop execution to run after the event loop starts
EventLoop::defer($loop);

// Run the event loop to process async tasks
EventLoop::run();
