<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use function Amp\delay;
use Flow\Driver\AmpDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SwooleDriver;
use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;
use Flow\Flow\Flow;
use Swoole\Coroutine;

$randomDriver = random_int(1, 5);

if (1 === $randomDriver) {
    printf("Use AmpDriver\n");

    $driver = new AmpDriver();

    $job1 = static function (object $data): Generator {
        printf("*. #%d - Job 1 : Calculating %d + %d\n", $data['id'], $data['number'], $data['number']);

        // simulating calculating some "light" operation from 100 to 900 milliseconds as async generator
        $delay = random_int(1, 9) * 100;
        yield delay($delay);
        $result = $data['number'];
        $result += $result;

        if (1 === random_int(1, 5)) {
            throw new Error('Failure when processing "Job1"');
        }

        printf("*. #%d - Job 1 : Result for %d + %d = %d and took %d milliseconds\n", $data['id'], $data['number'], $data['number'], $result, $delay);

        $data['number'] = $result;
    };
} elseif (2 === $randomDriver) {
    printf("Use SwooleDriver\n");

    $driver = new SwooleDriver();

    $job1 = static function (object $data): void {
        printf("*. #%d - Job 1 : Calculating %d + %d\n", $data['id'], $data['number'], $data['number']);

        // simulating calculating some "light" operation from 100 to 900 milliseconds as async generator
        $delay = random_int(1, 9) * 100;
        Coroutine::sleep($delay / 1000);
        $result = $data['number'];
        $result += $result;

        if (1 === random_int(1, 5)) {
            throw new Error('Failure when processing "Job1"');
        }

        printf("*. #%d - Job 1 : Result for %d + %d = %d and took %d milliseconds\n", $data['id'], $data['number'], $data['number'], $result, $delay);

        $data['number'] = $result;
    };
} else {
    printf("Use ReactDriver\n");

    $driver = new ReactDriver();

    $job1 = static function (object $data): void {
        printf("*. #%d - Job 1 : Calculating %d + %d\n", $data['id'], $data['number'], $data['number']);

        // simulating calculating some "light"
        $result = $data['number'];
        $result += $result;

        if (1 === random_int(1, 5)) {
            throw new Error('Failure when processing "Job1"');
        }

        printf("*. #%d - Job 1 : Result for %d + %d = %d\n", $data['id'], $data['number'], $data['number'], $result);

        $data['number'] = $result;
    };
}

$job2 = static function (object $data): void {
    printf(".* #%d - Job 2 : Calculating %d * %d\n", $data['id'], $data['number'], $data['number']);

    // simulating calculating some "light" operation as anonymous function
    $result = $data['number'];
    $result *= $result;

    if (1 === random_int(1, 5)) {
        throw new Error('Failure when processing "Job2"');
    }

    printf(".* #%d - Job 2 : Result for %d * %d = %d\n", $data['id'], $data['number'], $data['number'], $result);

    $data['number'] = $result;
};

$errorJob = static function (object $data, Throwable $exception): void {
    printf(".* #%d - Error Job : Exception %s\n", $data['id'], $exception->getMessage());

    $data['number'] = null;
};


$flow = (new Flow($job1, $errorJob, new MaxIpStrategy(2), $driver))
    ->fn(new Flow($job2, $errorJob, new MaxIpStrategy(2), $driver));

$ipPool = new SplObjectStorage();

for ($i = 1; $i <= 5; ++$i) {
    $ip = new Ip(new ArrayObject(['id' => $i, 'number' => $i]));
    $ipPool->offsetSet($ip, true);
    $flow($ip, fn ($ip) => $ipPool->offsetUnset($ip));
}

$driver->tick(1, function () use ($driver, $ipPool) {
    if (0 === $ipPool->count()) {
        $driver->stop();
    }
});
$driver->start();
