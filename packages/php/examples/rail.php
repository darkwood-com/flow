<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use function Amp\delay;
use RFBP\Driver\AmpDriver;
use RFBP\Driver\ReactDriver;
use RFBP\Driver\SwooleDriver;
use RFBP\Ip;
use RFBP\IpStrategy\MaxIpStrategy;
use RFBP\Rail\ErrorRail;
use RFBP\Rail\Rail;
use RFBP\Rail\SequenceRail;
use Swoole\Coroutine;

$randomDriver = random_int(1, 3);
if (1 === $randomDriver) {
    printf("Use AmpDriver\n");

    $driver = new AmpDriver();

    $job1 = static function (object $data): Generator {
        printf("*. #%d : Calculating %d + %d\n", $data['id'], $data['number'], $data['number']);

        // simulating calculating some "light" operation from 100 to 900 milliseconds as async generator
        $delay = random_int(1, 9) * 100;
        yield delay($delay);
        $result = $data['number'];
        $result += $result;

        if (1 === random_int(1, 3)) {
            throw new Error('Failure when processing "Job1"');
        }

        printf("*. #%d : Result for %d + %d = %d and took %d milliseconds\n", $data['id'], $data['number'], $data['number'], $result, $delay);

        $data['number'] = $result;
    };
} elseif (2 === $randomDriver) {
    printf("Use SwooleDriver\n");

    $driver = new SwooleDriver();

    $job1 = static function (object $data): void {
        printf("*. #%d : Calculating %d + %d\n", $data['id'], $data['number'], $data['number']);

        // simulating calculating some "light" operation from 100 to 900 milliseconds as async generator
        $delay = random_int(1, 9) * 100;
        Coroutine::sleep($delay / 1000);
        $result = $data['number'];
        $result += $result;

        if (1 === random_int(1, 3)) {
            throw new Error('Failure when processing "Job1"');
        }

        printf("*. #%d : Result for %d + %d = %d and took %d milliseconds\n", $data['id'], $data['number'], $data['number'], $result, $delay);

        $data['number'] = $result;
    };
} else {
    printf("Use ReactDriver\n");

    $driver = new ReactDriver();

    $job1 = static function (object $data): void {
        printf("*. #%d : Calculating %d + %d\n", $data['id'], $data['number'], $data['number']);

        // simulating calculating some "light"
        $result = $data['number'];
        $result += $result;

        if (1 === random_int(1, 3)) {
            throw new Error('Failure when processing "Job1"');
        }

        printf("*. #%d : Result for %d + %d = %d\n", $data['id'], $data['number'], $data['number'], $result);

        $data['number'] = $result;
    };
}

$job2 = static function (object $data): void {
    printf(".* #%d : Calculating %d * %d\n", $data['id'], $data['number'], $data['number']);

    // simulating calculating some "light" operation as anonymous function
    $result = $data['number'];
    $result *= $result;

    printf(".* #%d : Result for %d * %d = %d\n", $data['id'], $data['number'], $data['number'], $result);

    $data['number'] = $result;
};

$errorJob = static function (object $data, Throwable $exception): void {
    printf(".* #%d : Exception %s\n", $data['id'], $exception->getMessage());

    $data['number'] = null;
};

$rails = new SequenceRail([
    new Rail($job1, new MaxIpStrategy(2), $driver),
    new Rail($job2, new MaxIpStrategy(2), $driver),
]);

$rail = new ErrorRail($rails, $errorJob, new MaxIpStrategy(2), $driver);

$ipPool = new SplObjectStorage();
$rail->pipe(static function ($ip) use ($ipPool) {
    $ipPool->offsetUnset($ip);
});

for ($i = 1; $i <= 5; ++$i) {
    $ip = new Ip(new ArrayObject(['id' => $i, 'number' => $i]));
    $ipPool->offsetSet($ip, true);
    $rail($ip);
}

$driver->tick(1, function () use ($driver, $ipPool) {
    if (0 === $ipPool->count()) {
        $driver->stop();
    }
});
$driver->run();
