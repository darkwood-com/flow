<?php

declare(strict_types=1);

namespace App\Tools;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use RuntimeException;

use function Castor\context;
use function Castor\run;
use function dirname;

#[AsTask(name: 'env', namespace: 'flow:tools', description: 'Open the locked Navi PHP development shell', aliases: ['env-dev'])]
function envDev(
    #[AsOption(description: 'Include the optional OpenSwoole extension')]
    bool $withOpenSwoole,
): int {
    $environment = realpath(getenv('DARKWOOD_NAVI_ENV_DIR') ?: dirname(__DIR__, 3) . '/Navi/tools/env');
    if ($environment === false || !is_file($environment . '/shell.nix') || !is_file($environment . '/flake.lock')) {
        throw new RuntimeException('Set DARKWOOD_NAVI_ENV_DIR to the tools/env directory of a pinned Navi checkout.');
    }

    return run(
        [
            'nix', '--extra-experimental-features', 'nix-command flakes',
            'develop', '--file', __DIR__ . '/shell.nix',
            '--argstr', 'naviEnv', $environment,
            '--arg', 'withOpenSwoole', $withOpenSwoole ? 'true' : 'false',
        ],
        context()->withAllowFailure()->withTty(true),
    )->getExitCode();
}
