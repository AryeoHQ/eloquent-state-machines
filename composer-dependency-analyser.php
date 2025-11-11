<?php

use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

$config = new Configuration;

return $config
    ->addPathToExclude(__DIR__.'/src/Tooling')
    ->ignoreErrorsOnPackage('aryeo/tooling-laravel', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
