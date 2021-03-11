<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Amp\Loop;
use RFBP\Pipe;
use RFBP\IP;

$job1 = static function (object $data) {
    printf("* #%d : Calculating %d\n", $data['id'], $data['number']);

    // simulating calculating some "light" operation from 10 to 90 milliseconds
    $delay = random_int(1, 9) * 10;
    yield new \Amp\Delayed($delay);
    $result = $data['number'];
    $result += $result;

    printf("* #%d : Result for %d is %d and took %d milliseconds\n", $data['id'], $data['number'], $result, $delay);

    $data['number'] = $result;

    return $data;
};


$job2 = static function (object $data) {
    printf("** #%d : Calculating %d\n", $data['id'], $data['number']);

    // simulating calculating some "heavy" operation from 10 to 90 milliseconds
    $delay = random_int(1, 9) * 100;
    yield new \Amp\Delayed($delay);
    $result = $data['number'];
    $result *= $result;

    printf("** #%d : Result for %d is %d and took %d milliseconds\n", $data['id'], $data['number'], $result, $delay);

    $data['number'] = $result;

    return $data;
};

$pipes = [
    new Pipe($job1, 2),
    new Pipe($job2, 4),
];

/** @var Array<IP> $ips */
$ips = [];
for($i = 1; $i < 10; $i++) {
    $ip = new IP(new ArrayObject(['id' => $i, 'number' => $i]));
    $ips[$ip->getId()] = $ip;
}

Loop::run(static function() use ($pipes, $ips) {
    Loop::repeat(1, static function() use ($pipes, $ips) {
        foreach ($ips as $ip) {
            // IP need to be processed by the pipe 1
            if($ip->getCurrentPipe() === 0) {
                $pipes[0]->run($ip);
            }

            // IP need to be processed by the pipe 2
            if($ip->getCurrentPipe() === 1) {
                $pipes[1]->run($ip);
            }

            // does IP were processed by the pipe ?
            if($ip->getCurrentPipe() === 2) {
                unset($ips[$ip->getId()]);
            }
        }

        if(empty($ips)) {
            Loop::stop();
        }

        //echo "*** tick ***\n";
    });
});
