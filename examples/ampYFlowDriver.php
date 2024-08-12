<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Amp\DeferredFuture;
use Amp\Future;
use Flow\AsyncHandler\AsyncHandler;
use Flow\AsyncHandler\YAsyncHandler;
use Flow\Driver\AmpDriver;
use Flow\Event;
use Flow\Examples\Data\YFlowData;
use Flow\Flow\Flow;
use Flow\Ip;
use Revolt\EventLoop;

// Define the Y-Combinator
$U = static fn (Closure $f) => $f($f);
$Y = static fn (Closure $f) => $U(static fn (Closure $x) => $f(static fn ($y) => $U($x)($y)));

$asyncFactorial = $Y(static function ($factorial) {
    return static function ($args) use ($factorial): Future{
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

$factorialYJobAfter = static function ($args): Future {
    [$data, $defer] = $args;
    return $defer(static function ($complete) use ($data, $defer) {
        printf("* #%d - Job : Result for factorial(%d) = %d\n", $data->id, $data->number, $data->result);

        $complete([new YFlowData($data->id, $data->number), $defer]);
    });
};

$driver = new AmpDriver();

$flow = (new Flow($asyncFactorial, null, null, null, new YAsyncHandler(), $driver))
    ->fn(new Flow($factorialYJobAfter, null, null, null, new AsyncHandler(), $driver))
;

$ip = new Ip(new YFlowData(5, 5, 5));
$flow($ip);

$flow->await();
