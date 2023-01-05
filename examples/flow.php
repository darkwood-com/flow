<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Flow\Driver\AmpDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SwooleDriver;
use Flow\Flow\Flow;
use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;

$randomDriver = random_int(1, 3);

if ($randomDriver === 1) {
    printf("Use AmpDriver\n");

    $driver = new AmpDriver();
} elseif ($randomDriver === 2) {
    printf("Use ReactDriver\n");

    $driver = new ReactDriver();
} else {
    printf("Use SwooleDriver\n");

    $driver = new SwooleDriver();
}

$job1 = static function (object $data) use ($driver): void {
    printf("*. #%d - Job 1 : Calculating %d + %d\n", $data['id'], $data['number'], $data['number']);

    // simulating calculating some "light" operation from 0.1 to 1 seconds
    $delay = random_int(1, 10) / 10;
    $driver->delay($delay);
    $result = $data['number'];
    $result += $result;

    // simulating 1 chance on 5 to produce an exception from the "light" operation
    if (1 === random_int(1, 5)) {
        throw new Error('Failure when processing "Job1"');
    }

    printf("*. #%d - Job 1 : Result for %d + %d = %d and took %.01f seconds\n", $data['id'], $data['number'], $data['number'], $result, $delay);

    $data['number'] = $result;
};

$job2 = static function (object $data) use ($driver): void {
    printf(".* #%d - Job 2 : Calculating %d * %d\n", $data['id'], $data['number'], $data['number']);

    // simulating calculating some "heavy" operation from from 1 to 3 seconds
    $delay = random_int(1, 3);
    $driver->delay($delay);
    $result = $data['number'];
    $result *= $result;

    // simulating 1 chance on 5 to produce an exception from the "heavy" operation
    if (1 === random_int(1, 5)) {
        throw new Error('Failure when processing "Job2"');
    }

    printf(".* #%d - Job 2 : Result for %d * %d = %d and took %.01f seconds\n", $data['id'], $data['number'], $data['number'], $result, $delay);

    $data['number'] = $result;
};

$errorJob = static function (object $data, Throwable $exception): void {
    printf(".* #%d - Error Job : Exception %s\n", $data['id'], $exception->getMessage());

    $data['number'] = null;
};

$flow = (new Flow($job1, $errorJob, new MaxIpStrategy(2), $driver))
    ->fn(new Flow($job2, $errorJob, new MaxIpStrategy(2), $driver));

$ipPool = new SplObjectStorage();

for ($i = 1; $i <= 5; $i++) {
    $ip = new Ip(new ArrayObject(['id' => $i, 'number' => $i]));
    $ipPool->offsetSet($ip, true);
    $flow($ip, fn ($ip) => $ipPool->offsetUnset($ip));
}

$driver->tick(1, function () use ($driver, $ipPool) {
    if ($ipPool->count() === 0) {
        $driver->stop();
    }
});
$driver->start();
