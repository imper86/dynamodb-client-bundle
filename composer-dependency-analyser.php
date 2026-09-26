<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return new Configuration()
    // Required at runtime by Symfony's PhpDocExtractor, never referenced directly.
    ->ignoreErrorsOnPackage('phpdocumentor/reflection-docblock', [ErrorType::UNUSED_DEPENDENCY])
    // Required at runtime by Symfony's AbstractObjectNormalizer on denormalization, never referenced directly.
    ->ignoreErrorsOnPackage('symfony/property-access', [ErrorType::UNUSED_DEPENDENCY])
;
