<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Set\TwigSetList;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;

return static function (RectorConfig $rectorConfig): void {
    // register single rule
    $rectorConfig->rule(TypedPropertyFromStrictConstructorRector::class);

    // here we can define, what sets of rules will be applied
    // tip: use "SetList" class to autocomplete sets with your IDE
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        LevelSetList::UP_TO_PHP_85,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        SymfonySetList::SYMFONY_81,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        TwigSetList::TWIG_24,
    ]);

    $rectorConfig->bootstrapFiles([__DIR__ . '/../../vendor/autoload.php']);

    $rectorConfig->paths([
        __DIR__ . '/../../config',
        __DIR__ . '/../../public',
        __DIR__ . '/../../src',
        __DIR__ . '/../../tests',
        // __DIR__ . '/../../tools',
    ]);

    // $rectorConfig->import(TwigSetList::TWIG_UNDERSCORE_TO_NAMESPACE);

    /*$parameters = $containerConfigurator->parameters();

    // paths to refactor; solid alternative to CLI arguments
    $parameters->set(Option::PATHS, [__DIR__ . '/../../src', __DIR__ . '/../../tests']);

    $parameters->set(Option::AUTOLOAD_PATHS, [
        __DIR__ . '/bin/.phpunit/phpunit',
    ]);

    // register single rule
    $services = $containerConfigurator->services();
    $services->set(NullableCompareToNullRector::class);*/
};
