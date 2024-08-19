<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Fixes text files based on given .editorconfig declarations', aliases: ['editorconfig-fixer'])]
function editorconfigFixer(): int
{
    return run(
        [__DIR__ . '/vendor/bin/editorconfig-fixer fix --suffix php ' . __DIR__ . '/../..'],
        allowFailure: true,
    )->getExitCode();
}
