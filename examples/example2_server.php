<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use RFBP\Rail;
use RFBP\Supervisor;
use RFBP\Transport\DoctrineIpTransport;
use Amp\Delayed;
use Doctrine\DBAL\DriverManager;

$addOneJob = static function (object $data): \Generator {
    $data['number']++;
    printf("Client %s : Add one %d\n", $data['client'], $data['number']);

    // simulating calculating some "light" operation from 10 to 90 milliseconds
    $delay = random_int(1, 9) * 10;
    yield new Delayed($delay);

    return $data;
};

$multbyTwoJob = static function(object $data): \Generator {
    $data['number'] *= 2;
    printf("Client %s : Mult by two %d\n", $data['client'], $data['number']);

    // simulating calculating some "heavy" operation from 4 to 6 seconds
    $delay = random_int(4, 6) * 1000;
    yield new Delayed($delay);

    return $data;
};

$minusThreeJob = static function (object $data): \Generator {
    $data['number'] -= 3;
    printf("Client %s : Minus three %d\n", $data['client'], $data['number']);

    // simulating calculating some "light" operation from 10 to 90 milliseconds
    $delay = random_int(1, 9) * 10;
    yield new Delayed($delay);

    return $data;
};

$errorJob = static function(): \Generator {
    yield;
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