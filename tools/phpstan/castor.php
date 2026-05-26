<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Execute PHPStan analysis', aliases: ['phpstan'])]
function phpstan(): int
{
    return run(
        [__DIR__ . '/vendor/bin/phpstan', 'analyse', '--configuration=' . __DIR__ . '/phpstan.neon', '--memory-limit=1024M'],
        context()->withAllowFailure(),
    )->getExitCode();
}
