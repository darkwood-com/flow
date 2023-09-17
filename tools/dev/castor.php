<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Start dev server', aliases: ['dev'])]
function dev(): int
{
    return run(
        ['docker-compose', '-f', __DIR__ . '/docker-compose.yml', 'up', '-d'],
        tty: true,
        allowFailure: true,
    )->getExitCode();
}
