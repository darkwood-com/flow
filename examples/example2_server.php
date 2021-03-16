<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use RFBP\Rail;
use RFBP\Supervisor;
use RFBP\Transport\DoctrineIpTransport;
use Amp\Delayed;
use Doctrine\DBAL\DriverManager;

$addOneJob = static function (object $data): \Generator {
    printf("Client %s : Add one %d\n", $data['client'], $data['number']);

    // simulating calculating some "light" operation from 10 to 90 milliseconds as async generator
    $delay = random_int(1, 9) * 10;
    yield new Delayed($delay);
    $data['number']++;
};

$multbyTwoJob = static function(object $data): \Generator {
    printf("Client %s : Mult by two %d\n", $data['client'], $data['number']);

    // simulating calculating some "heavy" operation from 4 to 6 seconds as async generator
    $delay = random_int(4, 6) * 1000;
    yield new Delayed($delay);
    $data['number'] *= 2;

    // simulating 1 chance on 3 to produce an exception from the "heavy" operation
    if(random_int(1, 3) === 1) {
        throw new Error('Failure when processing "Mult by two"');
    }
};

$minusThreeJob = static function (object $data): void {
    printf("Client %s : Minus three %d\n", $data['client'], $data['number']);

    // simulating calculating some "light" operation as anonymous function
    $data['number'] -= 3;
};

$errorJob = static function(object $data, \Throwable $exception): void {
    printf("Client %s : Exception %s\n", $data['client'], $exception->getMessage());
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

$supervisor->start();