<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Run PHP Code Sniffer', aliases: ['phpcs'])]
function phpcs(): int
{
    return run(
        [__DIR__ . '/vendor/bin/phpcs', '--standard=' . __DIR__ . '/phpcs.xml'],
        allowFailure: true,
    )->getExitCode();
}
