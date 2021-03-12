<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use RFBP\Rail;
use RFBP\Supervisor;
use Amp\Delayed;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransport;

$addOneJob = static function ($struct): \Generator {
    $struct['number']++;
    printf("Client %d : Add one %d\n", $struct['client'], $struct['number']);

    yield new Delayed(1000);

    return $struct;
};

$multbyTwoJob = static function($struct): \Generator {
    $struct['number'] *= 2;
    printf("Client %d : Mult by two %d\n", $struct['client'], $struct['number']);

    yield new Delayed(2000);

    return $struct;
};

$minusThreeJob = static function ($struct): \Generator {
    $struct['number'] -= 3;
    printf("Client %d : Minus three %d\n", $struct['client'], $struct['number']);

    yield new Delayed(1000);

    return $struct;
};

$rails = [
    new Rail($addOneJob, 1),
    new Rail($multbyTwoJob, 3),
    new Rail($minusThreeJob, 2),
];

$transport = new RedisTransport(Connection::fromDsn('redis://localhost:6379'));

class ErrorRail {

}
$error = new ErrorRail();

$supervisor = new Supervisor(
    $transport,
    $transport,
    $rails,
    $error
);

$supervisor->start();