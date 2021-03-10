<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Amp\Loop;
use RFBP\Pipe;
use RFBP\IP;

$job = static function (object $data) {
    printf("Calculating number %d\n", $data['number']);

    // simulating calculating heavy operation from 1 to 9 seconds
    $delay = random_int ( 1, 9 ) * 1000;
    yield new \Amp\Delayed($delay);
    $result = $data['number'];
    $result *= $result;

    printf("Result for number %d is %d and took %d seconds\n", $data['number'], $result, $delay/1000);

    $data['number'] = $result;

    return $data;
};


$pipe = new Pipe($job, 2);

for($i = 1; $i < 10; $i++) {
    /*$ip = new IP(new ArrayObject(['number' => $i]));
    $pipe->run($ip);*/
    \Amp\asyncCall($job);
}

Loop::run();

/*Loop::repeat(1000, static function() use ($pipe) {
    //$pipe->run();
});*/