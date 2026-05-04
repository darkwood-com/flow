<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Run Phan', aliases: ['phan'])]
function phan(): int
{
    return run(
        [__DIR__ . '/vendor/bin/phan', '--config-file=' . __DIR__ . '/phan.php'],
        allowFailure: true,
    )->getExitCode();
}
