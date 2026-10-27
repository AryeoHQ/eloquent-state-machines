<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\Composer\ClassMap\Collectors;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use Tooling\Composer\ClassMap\Collectors\Contracts\Collector;
use Tooling\Composer\ClassMap\Collectors\Provides\Fakeable;

class StateMachineables implements Collector
{
    use Fakeable;

    /**
     * @param  array<class-string, \ReflectionClass<*>>  $reflections
     */
    public function collects(ReflectionClass $class, array $reflections): bool
    {
        return ! str_contains($class->getName(), 'Fixtures\\Tooling')
            && $class->isSubclassOf(Model::class)
            && ! $class->isAbstract();
    }
}
