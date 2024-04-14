<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Flow\Driver\AmpDriver;
use Flow\Driver\FiberDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SpatieDriver;
use Flow\Driver\SwooleDriver;
use Flow\Examples\Transport\DoctrineIpTransport;
use Flow\Flow\Flow;
use Flow\Flow\TransportFlow;
use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;

$driver = match (random_int(1, 4)) {
    1 => new AmpDriver(),
    2 => new FiberDriver(),
    3 => new ReactDriver(),
    4 => new SwooleDriver(),
    // 5 => new SpatieDriver(),
};
printf("Use %s\n", $driver::class);

$addOneJob = static function (object $data) use ($driver): void {
    printf("Client %s #%d : Calculating %d + 1\n", $data['client'], $data['id'], $data['number']);

    // simulating calculating some "light" operation from 0.1 to 1 seconds
    $delay = random_int(1, 10) / 10;
    $driver->delay($delay);
    $data['number']++;
};

$multbyTwoJob = static function (object $data) use ($driver): void {
    printf("Client %s #%d : Calculating %d * 2\n", $data['client'], $data['id'], $data['number']);

    // simulating calculating some "heavy" operation from from 1 to 3 seconds
    $delay = random_int(1, 3);
    $driver->delay($delay);

    // simulating 1 chance on 3 to produce an exception from the "heavy" operation
    if (1 === random_int(1, 3)) {
        throw new Error('Failure when processing "Mult by two"');
    }

    $data['number'] *= 2;
};

$minusThreeJob = static function (object $data): void {
    printf("Client %s #%d : Calculating %d - 3\n", $data['client'], $data['id'], $data['number']);

    // simulating calculating some "instant" operation
    $data['number'] -= 3;
};

/**
 * @param Ip<ArrayObject> $ip
 */
$errorJob = static function (Ip $ip, Throwable $exception): void {
    printf("Client %s #%d : Exception %s\n", $ip->data['client'], $ip->data['id'], $exception->getMessage());

    $ip->data->offsetSet('number', null);
};

$flow = Flow::do(static function () use ($addOneJob, $multbyTwoJob, $minusThreeJob, $errorJob) {
    yield [$addOneJob, $errorJob, new MaxIpStrategy(1)];
    yield [$multbyTwoJob, $errorJob, new MaxIpStrategy(3)];
    yield [$minusThreeJob, $errorJob, new MaxIpStrategy(2)];
}, ['driver' => $driver]);

$connection = DriverManager::getConnection(['url' => 'mysql://flow:flow@127.0.0.1:3306/flow?serverVersion=8.1']);
$transport = new DoctrineIpTransport($connection);

$transportFlow = new TransportFlow(
    $flow,
    $transport,
    $transport,
    $driver
);
$transportFlow->pull(1);
