<?php

declare(strict_types=1);
use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->ignoreVCSIgnored(true)
    ->ignoreDotFiles(false)
    ->in(dirname(__DIR__, 2))
    ->append([
        __FILE__,
    ])
    ->notPath('.castor.stub.php')
;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP82Migration' => true,
        '@PhpCsFixer' => true, // https://cs.symfony.com/doc/ruleSets/PhpCsFixer.html
        '@PhpCsFixer:risky' => true, // https://cs.symfony.com/doc/ruleSets/PhpCsFixerRisky.html
        '@PHPUnit100Migration:risky' => true, // https://cs.symfony.com/doc/ruleSets/PHPUnit100MigrationRisky.html
        'heredoc_indentation' => false,
        'php_unit_internal_class' => false, // From @PhpCsFixer but we don't want it
        'php_unit_test_class_requires_covers' => false, // From @PhpCsFixer but we don't want it
        'phpdoc_add_missing_param_annotation' => false, // From @PhpCsFixer but we don't want it
        'phpdoc_to_comment' => false, // We want PHPStan keep pass with anotation line comments
        'concat_space' => ['spacing' => 'one'],
        'ordered_class_elements' => true, // Symfony(PSR12) override the default value, but we don't want
        'blank_line_before_statement' => true, // Symfony(PSR12) override the default value, but we don't want
        'declare_strict_types' => true, // https://cs.symfony.com/doc/rules/strict/declare_strict_types.html
        'global_namespace_import' => [
            'import_constants' => true,
            'import_functions' => true,
            'import_classes' => true,
        ],
        'logical_operators' => false, // https://cs.symfony.com/doc/rules/operator/logical_operators.html prefer use 'or' and 'and' operators by design
        'yoda_style' => false, // https://cs.symfony.com/doc/rules/control_structure/yoda_style.html
        'increment_style' => ['style' => 'post'],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
;
