<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Flow\AsyncHandler\DeferAsyncHandler;
use Flow\Driver\AmpDriver;
use Flow\Driver\FiberDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SpatieDriver;
use Flow\Driver\SwooleDriver;
use Flow\Examples\Data\YFlowData;
use Flow\Flow\Flow;
use Flow\Flow\YFlow;
use Flow\Ip;

$driver = match (random_int(1, 4)) {
    1 => new AmpDriver(),
    2 => new ReactDriver(),
    3 => new FiberDriver(),
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
}

$factorialJob = static function (YFlowData $data): YFlowData {
    printf("*... #%d - Job 1 : Calculating factorial(%d)\n", $data->id, $data->number);

    // raw factorial calculation
    $result = factorial($data->number);

    printf("*... #%d - Job 1 : Result for factorial(%d) = %d\n", $data->id, $data->number, $result);

    return new YFlowData($data->id, $data->number);
};

$factorialYJobBefore = static function (YFlowData $data): YFlowData {
    printf(".*.. #%d - Job 2 : Calculating factorialYJob(%d)\n", $data->id, $data->number);

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
    printf(".*.. #%d - Job 2 : Result for factorialYJob(%d) = %d\n", $data->id, $data->number, $data->result);

    return new YFlowData($data->id, $data->number);
};

$factorialYMemoJob = static function (YFlowData $data): YFlowData {
    printf("..*. #%d - Job 3 : Calculating factorialYMemo(%d)\n", $data->id, $data->number);

    $result = factorialYMemo($data->number);

    printf("..*. #%d - Job 3 : Result for factorialYMemo(%d) = %d\n", $data->id, $data->number, $result);

    return new YFlowData($data->id, $data->number);
};

// Define the Y-Combinator
$U = static fn (Closure $f) => $f($f);
$Y = static fn (Closure $f) => $U(static fn (Closure $x) => $f(static fn ($y) => $U($x)($y)));

$factorialYJobDeferBefore = static function (YFlowData $data) {
    printf("...* #%d - Job 4 : Calculating factorialYJobDefer(%d)\n", $data->id, $data->number);

    return new YFlowData($data->id, $data->number, $data->number);
};

$factorialYJobDefer = $Y(static function ($factorial) {
    return static function ($args) use ($factorial) {
        [$data, $defer] = $args;

        return $defer(static function ($complete, $async) use ($data, $defer, $factorial) {
            if ($data->result <= 1) {
                $complete([new YFlowData($data->id, $data->number, 1), $defer]);
            } else {
                $async($factorial([new YFlowData($data->id, $data->number, $data->result - 1), $defer]), static function ($result) use ($data, $complete) {
                    [$resultData, $defer] = $result;
                    $complete([new YFlowData($data->id, $data->number, $data->result * $resultData->result), $defer]);
                });
            }
        });
    };
});

$factorialYJobDeferAfter = static function ($args) {
    [$data, $defer] = $args;

    return $defer(static function ($complete) use ($data, $defer) {
        printf("...* #%d - Job 4 : Result for factorialYJobDefer(%d) = %d\n", $data->id, $data->number, $data->result);

        $complete([new YFlowData($data->id, $data->number), $defer]);
    });
};

$flow = Flow::do(static function () use (
    $factorialJob,
    $factorialYJobBefore,
    $factorialYJob,
    $factorialYJobAfter,
    $factorialYMemoJob,
    $factorialYJobDeferBefore,
    $factorialYJobDefer,
    $factorialYJobDeferAfter
) {
    yield [$factorialJob];
    yield [$factorialYJobBefore];
    yield new YFlow($factorialYJob);
    yield [$factorialYJobAfter];
    yield [$factorialYMemoJob];
    yield [$factorialYJobDeferBefore];
    yield [$factorialYJobDefer, null, null, null, new DeferAsyncHandler()];
    yield [$factorialYJobDeferAfter, null, null, null, new DeferAsyncHandler()];
}, ['driver' => $driver]);

for ($i = 1; $i <= 5; $i++) {
    $ip = new Ip(new YFlowData($i, $i));
    $flow($ip);
}

$flow->await();
