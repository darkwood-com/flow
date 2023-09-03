<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\run;
use function dirname;

#[AsTask(description: 'Check and fix coding styles using PHP CS Fixer', aliases: ['cs-fix'])]
function csFix(
    #[AsOption(description: 'Only shows which files would have been modified.')]
    bool $dryRun,
): int {
    $command = [__DIR__ . '/vendor/bin/php-cs-fixer', 'fix', '--config', dirname(__DIR__, 2) . '/.php-cs-fixer.php'];

    if ($dryRun) {
        $command[] = '--dry-run';
    }

    return run($command, allowFailure: true)->getExitCode();
}
