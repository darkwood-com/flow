<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use RFBP\Rail;
use RFBP\Supervisor;
use Amp\Delayed;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransport;

$addOneJob = static function (object $data): \Generator {
    $data['number']++;
    printf("Client %s : Add one %d\n", $data['client'], $data['number']);

    yield new Delayed(1000);

    return $data;
};

$multbyTwoJob = static function(object $data): \Generator {
    $data['number'] *= 2;
    printf("Client %s : Mult by two %d\n", $data['client'], $data['number']);

    yield new Delayed(2000);

    return $data;
};

$minusThreeJob = static function (object $data): \Generator {
    $data['number'] -= 3;
    printf("Client %s : Minus three %d\n", $data['client'], $data['number']);

    yield new Delayed(1000);

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

$receiver = new RedisTransport(Connection::fromDsn('redis://localhost:6379/supervisor-messages'));
$sender = new RedisTransport(Connection::fromDsn('redis://localhost:6379/client-messages'));

$supervisor = new Supervisor(
    $receiver,
    $sender,
    $rails,
    $error
);

$supervisor->start();