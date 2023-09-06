<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Execute PHPStan analysis', aliases: ['phpstan'])]
function phpstan(): int
{
    return run(
        [__DIR__ . '/vendor/bin/phpstan', '--configuration=' . __DIR__ . '/phpstan.neon'],
        allowFailure: true,
    )->getExitCode();
}
