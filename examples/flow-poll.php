<?php

declare(strict_types=1);

use Flow\Driver\PollDriver;
use Flow\ExceptionInterface;
use Flow\Flow\Flow;
use Flow\Ip;
use Flow\IpStrategy\MaxIpStrategy;

require dirname(__DIR__) . '/vendor/autoload.php';

$driver = new PollDriver();
$sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
if ($sockets === false) {
    throw new RuntimeException('Could not create a local socket pair.');
}
[$reader, $writer] = $sockets;

try {
    foreach ($sockets as $socket) {
        stream_context_set_option($socket, 'stream', 'error_mode', StreamErrorMode::Exception);
        stream_set_blocking($socket, false);
    }

    $flow = new Flow(
        job: static function (string $operation) use ($driver, $reader, $writer): Generator {
            if ($operation === 'write') {
                yield $driver->waitDelay(0.02);
                if (!(yield $driver->waitWritable($writer, 1.0))) {
                    return 'Write timed out';
                }
                fwrite($writer, 'ping');

                return 'Sent ping';
            }

            $ready = yield $driver->waitReadable($reader, 1.0);
            $message = $ready ? fread($reader, 4) : '';
            $readyAgain = yield $driver->waitReadable($reader, 0.03);

            return sprintf('Read %s, then %s', $message, $readyAgain ? 'ready' : 'timed out');
        },
        errorJob: static function (ExceptionInterface $failure): void {
            // Raise a job error to infrastructure level for this CLI example.
            throw $failure->getPrevious() ?? $failure;
        },
        ipStrategy: new MaxIpStrategy(2),
        driver: $driver,
    )->fn(static function (string $message): void {
        echo $message, PHP_EOL;
    });

    $flow(new Ip('read'));
    $flow(new Ip('write'));
    $flow->await();
} finally {
    fclose($reader);
    fclose($writer);
}

$context = stream_context_create(['stream' => ['error_mode' => StreamErrorMode::Exception]]);
try {
    fopen(__FILE__ . '/missing', 'rb', context: $context);
} catch (StreamException $exception) {
    foreach ($exception->getErrors() as $error) {
        echo 'Stream error: ', $error->code->name, PHP_EOL;
    }
}
