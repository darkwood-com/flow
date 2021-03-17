<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Amp\Loop;
use Amp\Delayed;
use RFBP\Rail;
use RFBP\IP;

$job1 = static function (object $data): \Generator {
    printf("*. #%d : Calculating number %d\n", $data['id'], $data['number']);

    // simulating calculating some "light" operation from 10 to 90 milliseconds
    $delay = random_int(1, 9) * 10;
    yield new Delayed($delay);
    $result = $data['number'];
    $result += $result;

    printf("*. #%d : Result for number %d is %d and took %d milliseconds\n", $data['id'], $data['number'], $result, $delay);

    $data['number'] = $result;

    return $data;
};


$job2 = static function (object $data): \Generator {
    printf(".* #%d : Calculating number %d\n", $data['id'], $data['number']);

    // simulating calculating some "heavy" operation from 100 to 900 milliseconds
    $delay = random_int(1, 9) * 100;
    yield new Delayed($delay);
    $result = $data['number'];
    $result *= $result;

    printf(".* #%d : Result for number %d is %d and took %d milliseconds\n", $data['id'], $data['number'], $result, $delay);

    $data['number'] = $result;

    return $data;
};

$rails = [
    new Rail($job1, 2),
    new Rail($job2, 4),
];

/** @var array<IP> $ips */
$ips = [];
for($i = 1; $i < 5; $i++) {
    $ip = new IP(new ArrayObject(['id' => $i, 'number' => $i]));
    $ips[$ip->getId()] = $ip;
}

Loop::repeat(1, static function() use ($rails, $ips) {
    foreach ($ips as $ip) {
        // IP need to be processed by the rail 1
        if($ip->getRailIndex() === 0) {
            $rails[0]($ip);
        }

        // IP need to be processed by the rail 2
        if($ip->getRailIndex() === 1) {
            $rails[1]($ip);
        }

        // IP were processed by the rails ?
        if($ip->getRailIndex() === 2) {
            unset($ips[$ip->getId()]);
        }
    }

    if(empty($ips)) {
        Loop::stop();
    }

    //echo "*** tick ***\n";
});

Loop::run();
