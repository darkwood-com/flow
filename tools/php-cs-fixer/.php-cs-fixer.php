<?php

declare(strict_types=1);
use PhpCsFixer\Config;
use PhpCsFixer\Config\RuleCustomisationPolicyInterface;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->ignoreVCSIgnored(true)
    ->ignoreDotFiles(false)
    ->in(dirname(__DIR__, 2))
    ->append([
        __FILE__,
    ])
    ->notPath('.castor.stub.php')
    ->notPath('#^config/(bundles|preload|reference)\.php$#') // Symfony Flex / auto-generated; do not hand-fix
    ->notPath('public/index.php') // Symfony Flex front controller
    ->notPath('tools/phpstan/bootstrap.php') // Exclude: class must be named 'co' for PHPStan, not 'bootstrap'
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
        'modernize_types_casting' => false, // https://cs.symfony.com/doc/rules/cast_notation/modernize_types_casting.html
    ])
    ->setRuleCustomisationPolicy(new class implements RuleCustomisationPolicyInterface {
        public function getPolicyVersionForCache(): string
        {
            return hash_file('xxh128', __FILE__);
        }

        public function getRuleCustomisers(): array
        {
            return [
                'void_return' => static function (SplFileInfo $file) {
                    if (!$file instanceof Symfony\Component\Finder\SplFileInfo) {
                        return false;
                    }

                    $relativePathname = $file->getRelativePathname();

                    if (
                        str_contains($relativePathname, '/Tests/')
                        || str_contains($relativePathname, '/tests/')
                        || str_contains($relativePathname, '/Test/')
                    ) {
                        return false;
                    }

                    return true;
                },
            ];
        }
    })
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
;
