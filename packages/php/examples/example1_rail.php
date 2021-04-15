<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use function Amp\delay;
use RFBP\Driver\AmpDriver;
use RFBP\Driver\ReactDriver;
use RFBP\Driver\SwooleDriver;
use RFBP\Rail;
use Swoole\Coroutine;
use Symfony\Component\Messenger\Envelope as Ip;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp as IpIdStamp;

$randomDriver = random_int(1, 3);
if (1 === $randomDriver) {
    printf("Use AmpDriver\n");

    $driver = new AmpDriver();

    $job1 = static function (object $data): Generator {
        printf("*. #%d : Calculating %d + %d\n", $data['id'], $data['number'], $data['number']);

        // simulating calculating some "light" operation from 100 to 900 milliseconds as async generator
        $delay = random_int(1, 9) * 100;
        yield delay($delay);
        $result = $data['number'];
        $result += $result;

        printf("*. #%d : Result for %d + %d = %d and took %d milliseconds\n", $data['id'], $data['number'], $data['number'], $result, $delay);

        $data['number'] = $result;
    };
} elseif (2 === $randomDriver) {
    printf("Use SwooleDriver\n");

    $driver = new SwooleDriver();

    $job1 = static function (object $data) {
        printf("*. #%d : Calculating %d + %d\n", $data['id'], $data['number'], $data['number']);

        // simulating calculating some "light" operation from 100 to 900 milliseconds as async generator
        $delay = random_int(1, 9) * 100;
        Coroutine::sleep($delay / 1000);
        $result = $data['number'];
        $result += $result;

        printf("*. #%d : Result for %d + %d = %d and took %d milliseconds\n", $data['id'], $data['number'], $data['number'], $result, $delay);

        $data['number'] = $result;
    };
} else {
    printf("Use ReactDriver\n");

    $driver = new ReactDriver();

    $job1 = static function (object $data) {
        printf("*. #%d : Calculating %d + %d\n", $data['id'], $data['number'], $data['number']);

        // simulating calculating some "light"
        $result = $data['number'];
        $result += $result;

        printf("*. #%d : Result for %d + %d = %d\n", $data['id'], $data['number'], $data['number'], $result);

        $data['number'] = $result;
    };
}

$job2 = static function (object $data): void {
    printf(".* #%d : Calculating %d * %d\n", $data['id'], $data['number'], $data['number']);

    // simulating calculating some "light" operation as anonymous function
    $result = $data['number'];
    $result *= $result;

    printf(".* #%d : Result for %d * %d = %d\n", $data['id'], $data['number'], $data['number'], $result);

    $data['number'] = $result;
};

$rails = [
    new Rail($job1, 2, $driver),
    new Rail($job2, 1, $driver),
];

$ipPool = new SplObjectStorage();
$rails[0]->pipe(static function ($ip, $e) use ($ipPool, $rails) {
    $ipPool->offsetSet($ip, $rails[1]);
});
$rails[1]->pipe(static function ($ip) use ($ipPool) {
    $ipPool->offsetUnset($ip);
});

for ($i = 1; $i < 5; ++$i) {
    $ip = Ip::wrap(new ArrayObject(['id' => $i, 'number' => $i]), [new IpIdStamp(uniqid('ip_', true))]);
    $ipPool->offsetSet($ip, $rails[0]);
}

$driver->tick(1, function () use ($driver, $ipPool) {
    foreach ($ipPool as $ip) {
        $rail = $ipPool[$ip];
        ($rail)($ip);
    }

    if (0 === $ipPool->count()) {
        $driver->stop();
    }

    //echo "*** tick ***\n";
});
$driver->run();
