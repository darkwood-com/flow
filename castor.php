<?php

declare(strict_types=1);

namespace Flow\Tools;

use Castor\Attribute\AsTask;

use function Castor\import;
use function Castor\run;

import(__DIR__ . '/tools/php-cs-fixer/castor.php');
import(__DIR__ . '/tools/phpstan/castor.php');

#[AsTask(description: 'Start dev server', aliases: ['dev'])]
function dev(): void
{
    run('docker-compose up -d', tty: true);
}

#[AsTask(description: 'Launch PHPUnit test suite', aliases: ['test'])]
function test(): void
{
    run(__DIR__ . '/vendor/bin/phpunit', tty: true);
}

#[AsTask(description: 'Start documentation server locally', aliases: ['docs-serve'])]
function docsServe(): void
{
    run(__DIR__ . '/docs/node_modules/.bin/hugo/hugo server -s docs/src -D', tty: true);
}
