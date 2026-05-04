<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Flow\AsyncHandler\DeferAsyncHandler;
use Flow\Driver\AmpDriver;
use Flow\Driver\FiberDriver;
use Flow\Driver\ParallelDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SpatieDriver;
use Flow\Driver\SwooleDriver;
use Flow\Examples\Model\YFlowData;
use Flow\Flow\YFlow;
use Flow\FlowFactory;
use Flow\Ip;
use Flow\Job\YJob;
use Flow\JobInterface;

$driver = match (random_int(1, 4)) {
    1 => new AmpDriver(),
    2 => new ReactDriver(),
    3 => new FiberDriver(),
    4 => new SwooleDriver(),
    // 5 => new SpatieDriver(),
    // 6 => new ParallelDriver(),
};

printf("Use %s\n", $driver::class);

$factorial = static function (int $n) use (&$factorial): int {
    return ($n <= 1) ? 1 : $n * $factorial($n - 1);
};

/**
 * @return JobInterface<mixed, mixed>
 */
$Ywrap = static function (callable $func, callable $wrapperFunc): JobInterface {
    $wrappedFunc = static fn ($recurse) => $wrapperFunc(static fn (...$args) => $func($recurse)(...$args));

    return new YJob($wrappedFunc);
};

$memoWrapperGenerator = static function (callable $f): Closure {
    static $cache = [];

    return static function ($y) use ($f, &$cache) {
        if (!isset($cache[$y])) {
            $cache[$y] = $f($y);
        }

        return $cache[$y];
    };
};

/**
 * @return JobInterface<mixed, mixed>
 */
$Ymemo = static function (callable $f) use ($Ywrap, $memoWrapperGenerator): JobInterface {
    return $Ywrap($f, $memoWrapperGenerator);
};

$factorialGen = static function (callable $func): Closure {
    return static function (int $n) use ($func): int {
        return ($n <= 1) ? 1 : $n * $func($n - 1);
    };
};

$factorialYMemo = static function (int $n) use ($Ymemo, $factorialGen): int {
    return $Ymemo($factorialGen)($n);
};

$factorialJob = static function (YFlowData $data) use ($factorial): YFlowData {
    printf("*.... #%d - Job 1 : Calculating factorial(%d)\n", $data->id, $data->number);

    // raw factorial calculation
    $result = $factorial($data->number);

    printf("*.... #%d - Job 1 : Result for factorial(%d) = %d\n", $data->id, $data->number, $result);

    return new YFlowData($data->id, $data->number);
};

$factorialYJobBefore = static function (YFlowData $data): YFlowData {
    printf(".*... #%d - Job 2 : Calculating factorialYJob(%d)\n", $data->id, $data->number);

    return new YFlowData($data->id, $data->number, $data->number);
};

$factorialYJob = static function ($factorial) {
    return static function (YFlowData $data) use ($factorial): YFlowData {
        return new YFlowData(
            $data->id,
            $data->number,
            ($data->number <= 1) ? 1 : $data->number * $factorial(new YFlowData($data->id, $data->number - 1, $data->result - 1))->result
        );
    };
};

$factorialYJobAfter = static function (YFlowData $data): YFlowData {
    printf(".*... #%d - Job 2 : Result for factorialYJob(%d) = %d\n", $data->id, $data->number, $data->result);

    return new YFlowData($data->id, $data->number);
};

$factorialYMemoJob = static function (YFlowData $data) use ($driver, $factorialYMemo): YFlowData {
    printf("..*.. #%d - Job 3 : Calculating factorialYMemo(%d)\n", $data->id, $data->number);

    $driver->delay(3);
    $result = $factorialYMemo($data->number);

    printf("..*.. #%d - Job 3 : Result for factorialYMemo(%d) = %d\n", $data->id, $data->number, $result);

    return new YFlowData($data->id, $data->number);
};

$factorialYJobDeferBefore = static function (YFlowData $data) {
    printf("...*. #%d - Job 4 : Calculating factorialYJobDefer(%d)\n", $data->id, $data->number);

    return new YFlowData($data->id, $data->number, $data->number);
};

$factorialYJobDefer = new YJob(static function ($factorial) use ($driver) {
    return static function ($args) use ($factorial, $driver) {
        [$data, $defer] = $args;

        return $defer(static function ($complete, $async) use ($data, $defer, $factorial, $driver) {
            if ($data->result <= 1) {
                $delay = random_int(1, 3);
                printf("...*. #%d - Job 4 : Step factorialYJobDefer(%d) with delay %d\n", $data->id, $data->number, $delay);
                $driver->delay($delay);
                $complete([new YFlowData($data->id, $data->number, 1), $defer]);
            } else {
                $async($factorial([new YFlowData($data->id, $data->number, $data->result - 1), $defer]), static function ($result) use ($data, $complete, $driver) {
                    [$resultData, $defer] = $result;
                    $delay = random_int(1, 3);
                    printf("...*. #%d - Job 4 : Step async factorialYJobDefer(%d) with delay %d\n", $data->id, $data->number, $delay);
                    $driver->delay($delay);
                    $complete([new YFlowData($data->id, $data->number, $data->result * $resultData->result), $defer]);
                });
            }
        });
    };
});

$factorialYJobDeferAfter = static function ($args) {
    [$data, $defer] = $args;

    return $defer(static function ($complete) use ($data, $defer) {
        printf("...*. #%d - Job 4 : Result for factorialYJobDefer(%d) = %d\n", $data->id, $data->number, $data->result);

        $complete([new YFlowData($data->id, $data->number), $defer]);
    });
};

$fibonacciYJobDeferBefore = static function (YFlowData $data) {
    printf("....* #%d - Job 5 : Calculating fibonacciYJobDefer(%d)\n", $data->id, $data->number);

    return new YFlowData($data->id, $data->number, $data->number);
};

$fibonacciYJobDefer = new YJob(static function ($fibonacci) use ($driver) {
    return static function ($args) use ($fibonacci, $driver) {
        [$data, $defer] = $args;

        return $defer(static function ($complete, $async) use ($data, $defer, $fibonacci, $driver) {
            if ($data->result <= 1) {
                $delay = random_int(1, 3);
                printf("....* #%d - Job 5 : Step fibonacciYJobDefer(%d) with delay %d\n", $data->id, $data->number, $delay);
                $driver->delay($delay);
                $complete([new YFlowData($data->id, $data->number, 1), $defer]);
            } else {
                $async($fibonacci([new YFlowData($data->id, $data->number, $data->result - 1), $defer]), static function ($result1) use ($data, $complete, $driver, $async, $fibonacci) {
                    [$resultData1, $defer1] = $result1;
                    $async($fibonacci([new YFlowData($data->id, $data->number, $data->result - 2), $defer1]), static function ($result2) use ($data, $complete, $driver, $resultData1) {
                        [$resultData2, $defer2] = $result2;
                        $delay = random_int(1, 3);
                        printf("....* #%d - Job 5 : Step async fibonacciYJobDefer(%d) with delay %d\n", $data->id, $data->number, $delay);
                        $driver->delay($delay);
                        $fibResult = $resultData1->result + $resultData2->result;
                        $complete([new YFlowData($data->id, $data->number, $fibResult), $defer2]);
                    });
                });
            }
        });
    };
});

$fibonacciYJobDeferAfter = static function ($args) {
    [$data, $defer] = $args;

    return $defer(static function ($complete) use ($data, $defer) {
        printf("....* #%d - Job 5 : Result for fibonacciYJobDefer(%d) = %d\n", $data->id, $data->number, $data->result);

        $complete([new YFlowData($data->id, $data->number), $defer]);
    });
};

$flow = (new FlowFactory())->create(static function () use (
    $factorialJob,
    $factorialYJobBefore,
    $factorialYJob,
    $factorialYJobAfter,
    $factorialYMemoJob,
    $factorialYJobDeferBefore,
    $factorialYJobDefer,
    $factorialYJobDeferAfter,
    $fibonacciYJobDeferBefore,
    $fibonacciYJobDefer,
    $fibonacciYJobDeferAfter
) {
    yield [$factorialJob];
    yield [$factorialYJobBefore];
    yield new YFlow($factorialYJob);
    yield [$factorialYJobAfter];
    yield [$factorialYMemoJob];
    yield [$factorialYJobDeferBefore];
    yield [$factorialYJobDefer, null, null, null, new DeferAsyncHandler()];
    yield [$factorialYJobDeferAfter, null, null, null, new DeferAsyncHandler()];
    yield [$fibonacciYJobDeferBefore];
    yield [$fibonacciYJobDefer, null, null, null, new DeferAsyncHandler()];
    yield [$fibonacciYJobDeferAfter, null, null, null, new DeferAsyncHandler()];
}, ['driver' => $driver]);

for ($i = 1; $i <= 5; $i++) {
    $ip = new Ip(new YFlowData($i, $i));
    $flow($ip);
}

$flow->await();
