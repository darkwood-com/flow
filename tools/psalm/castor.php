<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Run Psalm', aliases: ['psalm'])]
function psalm(): int
{
    return run(
        [__DIR__ . '/vendor/bin/psalm', ' --no-cache', '--config', __DIR__ . '/psalm.xml'],
        allowFailure: true,
    )->getExitCode();
}
