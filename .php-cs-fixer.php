<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->ignoreVCSIgnored(true)
    ->ignoreDotFiles(false)
    ->in(__DIR__)
    ->append([
        __FILE__,
    ])
    ->notPath('.castor.stub.php')
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP82Migration' => true,
        '@PhpCsFixer' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'heredoc_indentation' => false,
        'php_unit_internal_class' => false, // From @PhpCsFixer but we don't want it
        'php_unit_test_class_requires_covers' => false, // From @PhpCsFixer but we don't want it
        'phpdoc_add_missing_param_annotation' => false, // From @PhpCsFixer but we don't want it
        'concat_space' => ['spacing' => 'one'],
        'ordered_class_elements' => true, // Symfony(PSR12) override the default value, but we don't want
        'blank_line_before_statement' => true, // Symfony(PSR12) override the default value, but we don't want
        'declare_strict_types' => true, // https://cs.symfony.com/doc/rules/strict/declare_strict_types.html
        'global_namespace_import' => [
            'import_constants' => true,
            'import_functions' => true,
            'import_classes' => true,
        ],
        'yoda_style' => false, // https://cs.symfony.com/doc/rules/control_structure/yoda_style.html
        'increment_style' => ['style' => 'post'],
    ])
    ->setFinder($finder)
;
