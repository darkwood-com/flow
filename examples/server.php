<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use RFBP\Pipe;
use RFBP\Supervisor;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransport;

$addOne = static function ($struct) {
    $struct['number']++;
    printf("Client %d : Add one %d\n", $struct['client'], $struct['number']);

    yield \Amp\delay(1000);

    return $struct;
};

$multbyTwo = static function($struct) {
    $struct['number'] *= 2;
    printf("Client %d : Mult by two %d\n", $struct['client'], $struct['number']);

    yield \Amp\delay(2000);

    return $struct;
};

$minusThree = static function ($struct) {
    $struct['number'] -= 3;
    printf("Client %d : Minus three %d\n", $struct['client'], $struct['number']);

    yield \Amp\delay(1000);

    return $struct;
};

$pipes = [
    new Pipe($addOne, 1),
    new Pipe($multbyTwo, 3),
    new Pipe($minusThree, 2),
];

$transport = new RedisTransport(Connection::fromDsn('redis://localhost:6379'));

class ErrorPipe {

}
$error = new ErrorPipe();

$supervisor = new Supervisor(
    $transport,
    $transport,
    $pipes,
    $error
);

$supervisor->start();