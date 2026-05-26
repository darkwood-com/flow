<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Launch PHPUnit test suite', aliases: ['phpunit'])]
function phpunit(
    #[AsOption(description: 'Replace default result output with TestDox format')]
    bool $testDox,
    #[AsOption(description: 'Apply XDEBUG_MODE', autocomplete: ['coverage'])]
    ?string $xdebugMode,
    #[AsOption(description: 'Path to HTML coverage')]
    ?string $coverateHtmlPath,
    #[AsOption(description: 'Path to XML coverage')]
    ?string $coverateHtmlXml,
): int {
    $command = [__DIR__ . '/vendor/bin/phpunit', '--configuration=' . __DIR__ . '/phpunit.xml'];
    $ctx = context()->withAllowFailure();

    if ($xdebugMode !== null || $coverateHtmlPath !== null || $coverateHtmlXml !== null) {
        $ctx = $ctx->withEnvironment(['XDEBUG_MODE' => $xdebugMode ?? 'coverage']);
    }

    if ($testDox) {
        $command[] = '--testdox';
    }

    if ($coverateHtmlPath !== null) {
        $command[] = '--coverage-html';
        $command[] = $coverateHtmlPath;
    }

    if ($coverateHtmlXml !== null) {
        $command[] = '--coverage-clover';
        $command[] = $coverateHtmlXml;
    }

    return run($command, $ctx)->getExitCode();
}
