<?php

declare(strict_types=1);

namespace App\Tools;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Start dev server', aliases: ['env-dev'])]
function envDev(): int
{
    return run(
        ['nix shell github:loophp/nix-shell#env-php85 --extra-experimental-features nix-command --extra-experimental-features flakes'],
        context()->withAllowFailure()->withTty(true),
    )->getExitCode();
}
