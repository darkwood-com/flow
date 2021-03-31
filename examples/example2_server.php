<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use function Amp\delay;
use Doctrine\DBAL\DriverManager;
use RFBP\Examples\Transport\DoctrineIpTransport;
use RFBP\Rail;
use RFBP\Supervisor;

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

$minusThreeJob = static function (object $data): void {
    printf("Client %s #%d : Calculating %d - 3\n", $data['client'], $data['id'], $data['number']);

    // simulating calculating some "light" operation as anonymous function
    $data['number'] -= 3;
};

$errorJob = static function (object $data, Throwable $exception): void {
    printf("Client %s #%d : Exception %s\n", $data['client'], $data['id'], $exception->getMessage());

    $data['number'] = null;
};

$rails = [
    new Rail($addOneJob, 1),
    new Rail($multbyTwoJob, 3),
    new Rail($minusThreeJob, 2),
];

$error = new Rail($errorJob, 2);

$connection = DriverManager::getConnection(['url' => 'mysql://root:root@127.0.0.1:3306/rfbp?serverVersion=5.7']);
$transport = new DoctrineIpTransport($connection);

$supervisor = new Supervisor(
    $transport,
    $transport,
    $rails,
    $error
);

$supervisor->run();
