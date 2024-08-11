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

function wrapWithDeferred(Closure $job): Future
{
    $deferred = new DeferredFuture();

    // Queue the operation to be executed in the event loop
    EventLoop::queue(static function () use ($job, $deferred) {
        try {
            $job(static function ($return) use ($deferred) {
                $deferred->complete($return);
            }, static function (Future $future, $next) {
                $future->map($next);
            });
        } catch (Throwable $exception) {
            $deferred->complete(new RuntimeException($exception->getMessage(), $exception->getCode(), $exception));
        }
    });

    return $deferred->getFuture();
}

$asyncFactorial = $Y(static function ($factorial) {
    return static function (YFlowData $data) use ($factorial) {
        return wrapWithDeferred(static function ($complete, $async) use ($data, $factorial) {
            if ($data->result <= 1) {
                $complete(new YFlowData($data->id, $data->number, 1));
            } else {
                $async($factorial(new YFlowData($data->id, $data->number, $data->result - 1)), static function ($resultData) use ($data, $complete) {
                    $complete(new YFlowData($data->id, $data->number, $data->result * $resultData->result));
                });
            }
        });
    };
});

$factorialYJobAfter = static function (YFlowData $data): Future {
    return wrapWithDeferred(static function ($complete) use ($data) {
        printf("* #%d - Job : Result for factorial(%d) = %d\n", $data->id, $data->number, $data->result);

        $complete(new YFlowData($data->id, $data->number));
    });
};

$driver = new AmpDriver();

$flow = (new Flow($asyncFactorial, null, null, null, new YAsyncHandler(), $driver))
    ->fn(new Flow($factorialYJobAfter, null, null, null, new AsyncHandler(), $driver))
;

$ip = new Ip(new YFlowData(5, 5, 5));
$flow($ip);

$flow->await();
