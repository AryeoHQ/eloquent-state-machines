<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\Composer\ClassMap\Collectors;

use ReflectionClass;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Tooling\Composer\ClassMap\Collectors\Contracts\Collector;
use Tooling\Composer\ClassMap\Collectors\Provides\Fakeable;

class StateMachines implements Collector
{
    use Fakeable;

    /**
     * @param  array<class-string, \ReflectionClass<*>>  $reflections
     */
    public function collects(ReflectionClass $class, array $reflections): bool
    {
        return $class->isEnum()
            && ! str_contains($class->getName(), 'Fixtures\\Tooling')
            && $class->isSubclassOf(StateMachineable::class);
    }
}
