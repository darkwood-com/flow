<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Run PHP Mess Detector', aliases: ['phpmd'])]
function phpmd(): int
{
    return run(
        [__DIR__ . '/vendor/bin/phpmd', '../examples/,../src/,../tests/', 'text', __DIR__ . '/phpmd.xml'],
        allowFailure: true,
    )->getExitCode();
}
