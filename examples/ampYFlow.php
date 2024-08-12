<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Flow\AsyncHandler\DeferAsyncHandler;
use Flow\Driver\FiberDriver;
use Flow\Examples\Data\YFlowData;
use Flow\Flow\Flow;
use Flow\Ip;

// Define the Y-Combinator
$U = static fn (Closure $f) => $f($f);
$Y = static fn (Closure $f) => $U(static fn (Closure $x) => $f(static fn ($y) => $U($x)($y)));

$factorialYJobDeferBefore = static function (YFlowData $data) {
    printf("* #%d - Job : Calculating factorial(%d)\n", $data->id, $data->number);

    return new YFlowData($data->id, $data->number, $data->number);
};

$asyncFactorialDefer = $Y(static function ($factorial) {
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
        printf("* #%d - Job : Result for factorial(%d) = %d\n", $data->id, $data->number, $data->result);

        $complete([new YFlowData($data->id, $data->number), $defer]);
    });
};

$driver = new FiberDriver();

$flow = (new Flow($factorialYJobDeferBefore, null, null, null, null, $driver))
    ->fn(new Flow($asyncFactorialDefer, null, null, null, new DeferAsyncHandler(), $driver))
    ->fn(new Flow($factorialYJobDeferAfter, null, null, null, new DeferAsyncHandler(), $driver))
;

$ip = new Ip(new YFlowData(5, 5, 5));
$flow($ip);

$flow->await();
