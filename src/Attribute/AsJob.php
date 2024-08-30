<?php

declare(strict_types=1);

namespace Flow\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class AsJob
{
    public function __construct(
        public string $name = '',
        public string $description = '',
    ) {}
}
