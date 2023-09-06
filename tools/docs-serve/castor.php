<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsTask;

use function Castor\run;
use function dirname;

#[AsTask(description: 'Start documentation server locally', aliases: ['docs-serve'])]
function docsServe(): int
{
    return run(
        [dirname(__DIR__, 2) . '/docs/node_modules/.bin/hugo/hugo', 'server', '-s', dirname(__DIR__, 2) . '/docs/src', '-D'],
        tty: true
    )->getExitCode();
}
