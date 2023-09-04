<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Run Infection', aliases: ['infection'])]
function infection(): int
{
    return run(
        [__DIR__ . '/vendor/bin/infection', '--configuration=' . __DIR__ . '/infection.json'],
        allowFailure: true,
    )->getExitCode();
}
