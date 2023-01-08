<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__.'/examples')
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
;

$config = new PhpCsFixer\Config();
return $config
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_constants' => true,
            'import_functions' => true,
            'import_classes' => true,
        ],
        'yoda_style' => false,
        'increment_style' => ['style' => 'post'],
    ])
    ->setFinder($finder)
;