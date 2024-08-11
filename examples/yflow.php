<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Amp\Future;
use Flow\AsyncHandler\YAsyncHandler;
use Flow\Driver\AmpDriver;
use Flow\Driver\FiberDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SpatieDriver;
use Flow\Driver\SwooleDriver;
use Flow\Examples\Data\YFlowData;
use Flow\Flow\Flow;
use Flow\Flow\YFlow;
use Flow\Ip;

use function Amp\async;

$U = static fn ($f) => $f($f);
$Y = static fn (callable $f) => $U(static fn (Closure $x) => $f(static fn ($y) => $U($x)($y)));

$asyncFactorial = $Y(static function ($fact) {
    return static function ($n) use ($fact): Future {
        return async(static function () use ($n, $fact) {
            if ($n <= 1) {
                return 1;
            }

            $result = yield $fact($n - 1);

            return $n * $result;
        });
    };
});

$future = $asyncFactorial(5);

$future->map(static function ($result) {
    echo 'Factorial: ' . $result . PHP_EOL;
});

// $driver = new AmpDriver();
// $driver->

/*$factorialYJob = static function ($factorial) {
    return static function (YFlowData $data) use ($factorial): YFlowData {
        return new YFlowData(
            $data->id,
            $data->number,
            ($data->result <= 1) ? 1 : $data->result * $factorial(new YFlowData($data->id, $data->number, $data->result - 1))->result
        );
    };
};

$flow = (new Flow($factorialYJob, null, null, null, new YAsyncHandler(), $driver))
    ->fn(static function (YFlowData $data): YFlowData {
        printf("* #%d - Job 4 : Result for factorial(%d) = %d\n", $data->id, $data->number, $data->result);

        return new YFlowData($data->id, $data->number);
    });

$ip = new Ip(new YFlowData(5, 5, 5));
$flow($ip);

$flow->await();*/

/*$driver = match (random_int(1, 1)) {
    1 => new AmpDriver(),
    2 => new FiberDriver(),
    3 => new ReactDriver(),
    4 => new SwooleDriver(),
    // 5 => new SpatieDriver(),
};
printf("Use %s\n", $driver::class);

function factorial(int $n): int
{
    return ($n <= 1) ? 1 : $n * factorial($n - 1);
}

function Ywrap(callable $func, callable $wrapperFunc): Closure
{
    $U = static fn ($f) => $f($f);
    $Y = static fn (callable $f, callable $g) => $U(static fn (Closure $x) => $f($g(static fn ($y) => $U($x)($y))));

    return $Y($func, $wrapperFunc);
}

function memoWrapperGenerator(callable $f): Closure
{
    static $cache = [];

    return static function ($y) use ($f, &$cache) {
        if (!isset($cache[$y])) {
            $cache[$y] = $f($y);
        }

        return $cache[$y];
    };
}

function Ymemo(callable $f): Closure
{
    return Ywrap($f, 'memoWrapperGenerator');
}

function factorialGen(callable $func): Closure
{
    return static function (int $n) use ($func): int {
        return ($n <= 1) ? 1 : $n * $func($n - 1);
    };
}

function factorialYMemo(int $n): int
{
    return Ymemo('factorialGen')($n);
}*/

/*
use Amp\Promise;
use Amp\Deferred;

function Ywrap(callable $f, callable $wrapperFunc) {
    $U = fn($x) => $x($x);
    return $U(fn($x) => $f($wrapperFunc(fn($y) => Promise\wait($U($x)($y)))));
}

function asyncWrapper(callable $f) {
    return function($y) use ($f) {
        $deferred = new Deferred();
        $deferred->resolve($f($y));  // Resolve immediately
        return $deferred->promise();
    };
}

function Ymemo($f) {
    return Ywrap($f, 'asyncWrapper');
}

function factorialGen(callable $func) {
    return function (int $n) use ($func) {
        $deferred = new Deferred();
        $result = ($n <= 1) ? 1 : $n * Promise\wait($func($n - 1));
        $deferred->resolve($result);  // Resolve immediately
        return $deferred->promise();
    };
}

function factorialYMemo(int $n) {
    return Promise\wait(Ymemo('factorialGen')($n));
}

// Usage
Amp\Loop::run(function() {
    $result = factorialYMemo(5);
    echo $result; // Expected: 120
});
*/

/*
use Amp\Promise;

class Flow {
    // ... other combinators ...

    public static function Y($f) {
        return (function ($x) use ($f) {
            return $f(function ($y) use ($x) {
                return Promise\wait($x($x)($y));
            });
        })(function ($x) {
            return $f(function ($y) use ($x) {
                return Promise\wait($x($x)($y));
            });
        });
    }

    // ... rest of the class ...
}

use Amp\Loop;

Loop::run(function() {
    $factorial = Flow::Y(function ($f) {
        return function ($x) use ($f) {
            return $x == 0 ? 1 : $x * $f($x - 1);
        };
    });

    echo $factorial(5); // Outputs: 120
});
*/

// factorialYMemo(6) . ' ' . factorialYMemo(5);

/*$factorialJob = static function (YFlowData $data): YFlowData {
    printf("*... #%d - Job 1 : Calculating factorial(%d)\n", $data->id, $data->number);

    // raw factorial calculation
    $result = factorial($data->number);

    printf("*... #%d - Job 1 : Result for factorial(%d) = %d\n", $data->id, $data->number, $result);

    return new YFlowData($data->id, $data->number);
};

$factorialYJobBefore = static function (YFlowData $data): YFlowData {
    printf(".*.. #%d - Job 2 : Calculating factorial(%d)\n", $data->id, $data->number);

    return new YFlowData($data->id, $data->number, $data->number);
};

$factorialYJob = static function ($factorial) {
    return static function (YFlowData $data) use ($factorial): YFlowData {
        return new YFlowData(
            $data->id,
            $data->number,
            ($data->result <= 1) ? 1 : $data->result * $factorial(new YFlowData($data->id, $data->number, $data->result - 1))->result
        );
    };
};

$factorialYJobAfter = static function (YFlowData $data): YFlowData {
    printf(".*.. #%d - Job 2 : Result for factorial(%d) = %d\n", $data->id, $data->number, $data->result);

    return new YFlowData($data->id, $data->number);
};

$factorialYMemoJob = static function (YFlowData $data): YFlowData {
    printf("..*. #%d - Job 3 : Calculating factorialYMemo(%d)\n", $data->id, $data->number);

    $result = factorialYMemo($data->number);

    printf("..*. #%d - Job 3 : Result for factorialYMemo(%d) = %d\n", $data->id, $data->number, $result);

    return new YFlowData($data->id, $data->number);
};

$factorialYAsyncHandlerJobBefore = static function (YFlowData $data): YFlowData {
    printf("...* #%d - Job 4 : Calculating factorial(%d)\n", $data->id, $data->number);

    return new YFlowData($data->id, $data->number, $data->number);
};

$factorialYAsyncHandlerJobAfter = static function (YFlowData $data): YFlowData {
    printf("...* #%d - Job 4 : Result for factorial(%d) = %d\n", $data->id, $data->number, $data->result);

    return new YFlowData($data->id, $data->number);
};

$flow = Flow::do(static function () use (
    $factorialJob,
    $factorialYJobBefore,
    $factorialYJob,
    $factorialYJobAfter,
    $factorialYMemoJob,
    $factorialYAsyncHandlerJobBefore,
    $factorialYAsyncHandlerJobAfter
) {
    //yield [$factorialJob];
    //yield [$factorialYJobBefore];
    //yield new YFlow($factorialYJob);
    //yield [$factorialYJobAfter];
    //yield [$factorialYMemoJob];
    yield [$factorialYAsyncHandlerJobBefore];
    yield [$factorialYJob, null, null, null, new YAsyncHandler()];
    yield [$factorialYAsyncHandlerJobAfter];
}, ['driver' => $driver]);

for ($i = 5; $i <= 5; $i++) {
    $ip = new Ip(new YFlowData($i, $i));
    $flow($ip);
}

$flow->await();*/
