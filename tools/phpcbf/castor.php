<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Clean code with PHP Code Beautifier and Fixer', aliases: ['phpcbf'])]
function phpcbf(): int
{
    return run(
        [__DIR__ . '/vendor/bin/phpcbf', '--standard=' . __DIR__ . '/phpcs.xml'],
        allowFailure: true,
    )->getExitCode();
}
