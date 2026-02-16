<?php

use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

$config = new Configuration;

return $config
    ->ignoreErrorsOnPackage('aryeo/tooling-laravel', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
