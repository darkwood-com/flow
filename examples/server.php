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
use Flow\ExceptionInterface;
use Flow\Flow\TransportFlow;
use Flow\FlowFactory;
use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;
use Symfony\Component\Messenger\Envelope;

$driver = match (random_int(1, 4)) {
    1 => new AmpDriver(),
    2 => new FiberDriver(),
    3 => new ReactDriver(),
    4 => new SwooleDriver(),
    // 5 => new SpatieDriver(),
};
printf("Use %s\n", $driver::class);

$addOneJob = static function (Envelope $envelope) use ($driver): Envelope {
    $message = $envelope->getMessage();
    printf("Client %s #%d : Calculating %d + 1\n", $message['client'], $message['id'], $message['number']);

    // simulating calculating some "light" operation from 0.1 to 1 seconds
    $delay = random_int(1, 10) / 10;
    $driver->delay($delay);
    $message['number']++;

    return $envelope;
};

$multbyTwoJob = static function (Envelope $envelope) use ($driver): Envelope {
    $message = $envelope->getMessage();
    printf("Client %s #%d : Calculating %d * 2\n", $message['client'], $message['id'], $message['number']);

    // simulating calculating some "heavy" operation from from 1 to 3 seconds
    $delay = random_int(1, 3);
    $driver->delay($delay);

    // simulating 1 chance on 3 to produce an exception from the "heavy" operation
    if (1 === random_int(1, 3)) {
        throw new Error(sprintf('Client %s #%d : Exception Failure when processing "Mult by two"', $message['client'], $message['id']));
    }

    $message['number'] *= 2;

    return $envelope;
};

$minusThreeJob = static function (Envelope $envelope): Envelope {
    $message = $envelope->getMessage();
    printf("Client %s #%d : Calculating %d - 3\n", $message['client'], $message['id'], $message['number']);

    // simulating calculating some "instant" operation
    $message['number'] -= 3;

    return $envelope;
};

/**
 * @param Ip<ArrayObject> $ip
 */
$errorJob = static function (ExceptionInterface $exception): void {
    printf("%s\n", $exception->getMessage());
};

$flow = (new FlowFactory())->create(static function () use ($addOneJob, $multbyTwoJob, $minusThreeJob, $errorJob) {
    yield [$addOneJob, $errorJob, new MaxIpStrategy(1)];
    yield [$multbyTwoJob, $errorJob, new MaxIpStrategy(3)];
    yield [$minusThreeJob, $errorJob, new MaxIpStrategy(2)];
}, ['driver' => $driver]);

$connection = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'path' => __DIR__ . '/flow.sqlite',
]);
$transport = new DoctrineIpTransport($connection);

$transportFlow = new TransportFlow(
    $flow,
    $transport,
    $transport,
    $driver
);
$transportFlow->pull(1);
$flow->await();
