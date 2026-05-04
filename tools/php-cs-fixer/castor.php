<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Check and fix coding styles using PHP CS Fixer', aliases: ['php-cs-fixer'])]
function phpCsFixer(
    #[AsOption(description: 'Only shows which files would have been modified')]
    bool $dryRun,
): int {
    $command = [__DIR__ . '/vendor/bin/php-cs-fixer', 'fix', '--config', __DIR__ . '/.php-cs-fixer.php'];

    if ($dryRun) {
        $command[] = '--dry-run';
    }

    return run($command, allowFailure: true)->getExitCode();
}
