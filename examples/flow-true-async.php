<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Flow\Driver\TrueAsyncDriver;
use Flow\Flow\Flow;
use Flow\Ip;

if (!TrueAsyncDriver::isSupported()) {
    throw new RuntimeException('ext-async is required. Build PHP with the TrueAsync extension.');
}

$flow = (new Flow(
    job: static fn (int $n): int => $n * 2,
    driver: new TrueAsyncDriver(),
))->fn(static fn (int $n): int => $n + 1);

$flow(new Ip(21));
$flow->await();

printf("Result pipeline completed (expected final step input: 42)\n");
