<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use function Amp\delay;
use Doctrine\DBAL\DriverManager;
use RFBP\Driver\AmpDriver;
use RFBP\Driver\ReactDriver;
use RFBP\Driver\SwooleDriver;
use RFBP\Examples\Transport\DoctrineIpTransport;
use RFBP\IpStrategy\MaxIpStrategy;
use RFBP\Rail\ErrorRail;
use RFBP\Rail\Rail;
use RFBP\Rail\SequenceRail;
use RFBP\Rail\TransportRail;
use Swoole\Coroutine;

$randomDriver = random_int(1, 3);
if (1 === $randomDriver) {
    printf("Use AmpDriver\n");

    $driver = new AmpDriver();
} elseif (2 === $randomDriver) {
    printf("Use SwooleDriver\n");

    $driver = new SwooleDriver();
} else {
    printf("Use ReactDriver\n");

    $driver = new ReactDriver();
}

if (1 === $randomDriver) {
    $addOneJob = static function (object $data): Generator {
        printf("Client %s #%d : Calculating %d + 1\n", $data['client'], $data['id'], $data['number']);

        // simulating calculating some "light" operation from 10 to 90 milliseconds as async generator
        $delay = random_int(1, 9) * 10;
        yield delay($delay);
        ++$data['number'];
    };

    $multbyTwoJob = static function (object $data): Generator {
        printf("Client %s #%d : Calculating %d * 2\n", $data['client'], $data['id'], $data['number']);

        // simulating calculating some "heavy" operation from 4 to 6 seconds as async generator
        $delay = random_int(4, 6) * 1000;
        yield delay($delay);
        $data['number'] *= 2;

        // simulating 1 chance on 3 to produce an exception from the "heavy" operation
        if (1 === random_int(1, 3)) {
            throw new Error('Failure when processing "Mult by two"');
        }
    };
} elseif (2 === $randomDriver) {
    $addOneJob = static function (object $data): void {
        printf("Client %s #%d : Calculating %d + 1\n", $data['client'], $data['id'], $data['number']);

        // simulating calculating some "light" operation from 10 to 90 milliseconds as async generator
        $delay = random_int(1, 9) * 10;
        Coroutine::sleep($delay / 1000);
        ++$data['number'];
    };

    $multbyTwoJob = static function (object $data): void {
        printf("Client %s #%d : Calculating %d * 2\n", $data['client'], $data['id'], $data['number']);

        // simulating calculating some "heavy" operation from 4 to 6 seconds as async generator
        $delay = random_int(4, 6) * 1000;
        Coroutine::sleep($delay / 1000);
        $data['number'] *= 2;

        // simulating 1 chance on 3 to produce an exception from the "heavy" operation
        if (1 === random_int(1, 3)) {
            throw new Error('Failure when processing "Mult by two"');
        }
    };
} else {
    $addOneJob = static function (object $data): void {
        printf("Client %s #%d : Calculating %d + 1\n", $data['client'], $data['id'], $data['number']);

        // simulating calculating some "light" operation
        ++$data['number'];
    };

    $multbyTwoJob = static function (object $data): void {
        printf("Client %s #%d : Calculating %d * 2\n", $data['client'], $data['id'], $data['number']);

        // simulating calculating some "heavy" operation
        $data['number'] *= 2;

        // simulating 1 chance on 3 to produce an exception from the "heavy" operation
        if (1 === random_int(1, 3)) {
            throw new Error('Failure when processing "Mult by two"');
        }
    };
}

$minusThreeJob = static function (object $data): void {
    printf("Client %s #%d : Calculating %d - 3\n", $data['client'], $data['id'], $data['number']);

    // simulating calculating some "light" operation as anonymous function
    $data['number'] -= 3;
};

$errorJob = static function (object $data, Throwable $exception): void {
    printf("Client %s #%d : Exception %s\n", $data['client'], $data['id'], $exception->getMessage());

    $data['number'] = null;
};

$rail = new SequenceRail([
    new Rail($addOneJob, new MaxIpStrategy(1), $driver),
    new Rail($multbyTwoJob, new MaxIpStrategy(3), $driver),
    new Rail($minusThreeJob, new MaxIpStrategy(2), $driver),
]);

$rail = new ErrorRail($rail, $errorJob, new MaxIpStrategy(2), $driver);

$connection = DriverManager::getConnection(['url' => 'mysql://root:root@127.0.0.1:3306/rfbp?serverVersion=5.7']);
$transport = new DoctrineIpTransport($connection);

new TransportRail(
    $rail,
    $transport,
    $transport,
    $driver
);

$driver->run();
