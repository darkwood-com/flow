<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Flow\Driver\AmpDriver;
use Flow\Driver\FiberDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\RevoltDriver;
use Flow\Driver\SpatieDriver;
use Flow\Driver\SwooleDriver;
use Flow\Examples\Data;
use Flow\ExceptionInterface;
use Flow\Flow\Flow;
use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;

$driver = match (random_int(1, 6)) {
    1 => new AmpDriver(),
    2 => new FiberDriver(),
    3 => new ReactDriver(),
    4 => new RevoltDriver(),
    5 => new SpatieDriver(),
    6 => new SwooleDriver(),
};
printf("Use %s\n", $driver::class);

$job1 = static function (Data $data) use ($driver): void {
    printf("*. #%d - Job 1 : Calculating %d + %d\n", $data->id, $data->number, $data->number);

    // simulating calculating some "light" operation from 0.1 to 1 seconds
    $delay = random_int(1, 3);
    $driver->delay($delay);
    $result = $data->number;
    $result += $result;

    // simulating 1 chance on 5 to produce an exception from the "light" operation
    if (1 === random_int(1, 5)) {
        throw new Error('Failure when processing "Job1"');
    }

    printf("*. #%d - Job 1 : Result for %d + %d = %d and took %.01f seconds\n", $data->id, $data->number, $data->number, $result, $delay);

    $data->number = $result;
};

$job2 = static function (Data $data) use ($driver): void {
    printf(".* #%d - Job 2 : Calculating %d * %d\n", $data->id, $data->number, $data->number);

    // simulating calculating some "heavy" operation from from 1 to 3 seconds
    $delay = random_int(1, 3);
    $driver->delay($delay);
    $result = $data->number;
    $result *= $result;

    // simulating 1 chance on 5 to produce an exception from the "heavy" operation
    if (1 === random_int(1, 5)) {
        throw new Error('Failure when processing "Job2"');
    }

    printf(".* #%d - Job 2 : Result for %d * %d = %d and took %.01f seconds\n", $data->id, $data->number, $data->number, $result, $delay);

    $data->number = $result;
};

/**
 * @param Ip<Data> $ip
 */
$errorJob1 = static function (Ip $ip, ExceptionInterface $exception): void {
    printf("*. #%d - Error Job : Exception %s\n", $ip->data->id, $exception->getMessage());

    $ip->data->number = null;
};

/**
 * @param Ip<Data> $ip
 */
$errorJob2 = static function (Ip $ip, ExceptionInterface $exception): void {
    printf(".* #%d - Error Job : Exception %s\n", $ip->data->id, $exception->getMessage());

    $ip->data->number = null;
};

$flow = Flow::do(static function () use ($job1, $job2, $errorJob1, $errorJob2) {
    yield [$job1, $errorJob1, new MaxIpStrategy(2)];
    yield [$job2, $errorJob2, new MaxIpStrategy(2)];
}, ['driver' => $driver]);

$ipPool = new SplObjectStorage();

for ($i = 1; $i <= 5; $i++) {
    $ip = new Ip(new Data($i, $i));
    $ipPool->offsetSet($ip, true);
    $flow($ip, static fn ($ip) => $ipPool->offsetUnset($ip));
}

$driver->tick(1, static function () use ($driver, $ipPool) {
    if ($ipPool->count() === 0) {
        $driver->stop();
    }
});
$driver->start();
