<?php

declare(strict_types=1);

namespace App\Tools;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;
use function dirname;

#[AsTask(name: 'env', namespace: 'flow:tools', description: 'Open the locked Navi PHP development shell', aliases: ['env-dev'])]
function envDev(): int
{
    $environment = dirname(__DIR__, 3) . '/Navi/tools/env';
    if (!is_file($environment . '/flake.lock')) {
        return run(
            ['nix', '--extra-experimental-features', 'nix-command flakes', 'shell', 'github:loophp/nix-shell#env-php85'],
            context()->withAllowFailure()->withTty(true),
        )->getExitCode();
    }

    return run(
        ['nix', '--extra-experimental-features', 'nix-command flakes', 'develop', '--file', $environment . '/shell.nix', '--arg', 'lockFile', json_encode($environment . '/flake.lock', JSON_THROW_ON_ERROR)],
        context()->withAllowFailure()->withTty(true),
    )->getExitCode();
}
