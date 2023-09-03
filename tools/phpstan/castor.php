<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsTask;

use function Castor\run;
use function dirname;

#[AsTask(description: 'Execute PHPStan analysis', aliases: ['phpstan'])]
function phpstan(): int
{
    return run(
        [__DIR__ . '/vendor/bin/phpstan', '--configuration=' . dirname(__DIR__, 2) . '/phpstan.neon'],
        allowFailure: true,
    )->getExitCode();
}
