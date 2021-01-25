<?php

require __DIR__.'/vendor/autoload.php';

use RFBP\Pipe;
use RFBP\Client;
use RFBP\Supervisor;

$addOne = static function ($struct) {
    $struct['number']++;
    printf("Add one : %d\n", $struct['number']);

    yield $struct;
};

$multbyTwo = static function($struct) {
    $struct['number'] *= 2;
    printf("Mult by two : %d\n", $struct['number']);

    yield $struct;
};

$minusThree = static function ($struct) {
    $struct['number'] -= 3;
    printf("Minus three : %d\n", $struct['number']);

    yield $struct;
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

$client1->call(['number' => 1]);
$client1->call(['number' => 2]);

$supervisor->start();