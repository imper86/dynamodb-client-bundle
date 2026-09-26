<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;
use Rector\Carbon\Rector\New_\DateTimeInstanceToCarbonRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\Config\RectorConfig;
use Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameVariableToMatchNewTypeRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
        carbon: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
        symfonyConfigs: true,
    )
    ->withPhpSets(php85: true)
    ->withComposerBased(
        doctrine: true,
        phpunit: true,
        symfony: true,
    )
    ->withSkip(
        [
            CatchExceptionNameMatchingTypeRector::class,
            // The project does not depend on nesbot/carbon.
            DateTimeInstanceToCarbonRector::class,
            RenameParamToMatchTypeRector::class,
            RenameVariableToMatchNewTypeRector::class,
            RenamePropertyToMatchTypeRector::class,
            RenameVariableToMatchMethodCallReturnTypeRector::class,
            ThrowWithPreviousExceptionRector::class,
            PreferPHPUnitThisCallRector::class,
            NewlineBetweenClassLikeStmtsRector::class,
        ],
    )
;
