<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use RFBP\Pipe;
use RFBP\Client;
use RFBP\Supervisor;

$addOne = static function ($struct) {
    $struct['number']++;
    printf("Client %d : Add one %d\n", $struct['client'], $struct['number']);

    yield $struct;

    return $struct;
};

$multbyTwo = static function($struct) {
    $struct['number'] *= 2;
    printf("Client %d : Mult by two %d\n", $struct['client'], $struct['number']);

    yield $struct;

    return $struct;
};

$minusThree = static function ($struct) {
    $struct['number'] -= 3;
    printf("Client %d : Minus three %d\n", $struct['client'], $struct['number']);

    yield $struct;

    return $struct;
};

$pipes = [
    new Pipe($addOne, 1),
    new Pipe($multbyTwo, 3),
    new Pipe($minusThree, 2),
];

class ProducerPipe {
    protected $structs = [];

    public function send($struct) {
        $this->structs[] = $struct;
    }

    public function getDatas() {
        $structs = $this->structs;
        $this->structs = [];
        return $structs;
    }
}
$producer = new ProducerPipe();

class ConsumerPipe {
    protected $structs = [];

    public function receive($struct) {
        $this->structs[] = $struct;
    }

    public function getDatas() {
        $structs = $this->structs;
        $this->structs = [];
        return $structs;
    }
}
$consumer = new ConsumerPipe();

class ErrorPipe {

}
$error = new ErrorPipe();

$supervisor = new Supervisor(
    $producer,
    $consumer,
    $pipes,
    $error
);

$client1 = new Client($producer);
$client2 = new Client($producer);
$client3 = new Client($producer);
$client4 = new Client($producer);
$client5 = new Client($producer);
$client6 = new Client($producer);
$client7 = new Client($producer);
$client8 = new Client($producer);
$client9 = new Client($producer);

$client1->call(['client' => '1', 'number' => 1]);
$client2->call(['client' => '2', 'number' => 2]);
$client3->call(['client' => '3', 'number' => 3]);
$client4->call(['client' => '4', 'number' => 4]);
$client5->call(['client' => '5', 'number' => 5]);
$client6->call(['client' => '6', 'number' => 6]);
$client7->call(['client' => '7', 'number' => 7]);
$client8->call(['client' => '8', 'number' => 8]);
$client9->call(['client' => '9', 'number' => 9]);

$supervisor->start();