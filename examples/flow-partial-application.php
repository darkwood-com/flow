<?php

declare(strict_types=1);

use Flow\FlowFactory;
use Flow\Ip;

require dirname(__DIR__) . '/vendor/autoload.php';

function shiftNumber(int $number, int $by): int
{
    return $number + $by;
}

function scaleNumber(int $number, int $factor): int
{
    return $number * $factor;
}

$flow = (new FlowFactory())->create(static function () {
    yield shiftNumber(?, 1);
    yield scaleNumber(factor: 2, number: ?);
    yield sprintf('Result: %02d', ?);
    yield static function (string $message): void {
        echo $message, PHP_EOL;
    };
});

$flow(new Ip(3));
$flow->await();
