<?php

declare(strict_types=1);

namespace Flow\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class FlowExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void {}
}
