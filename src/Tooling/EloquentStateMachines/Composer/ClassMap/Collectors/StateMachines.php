<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\Composer\ClassMap\Collectors;

use Illuminate\Support\Collection;
use ReflectionClass;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Tooling\Composer\ClassMap\Collectors\Contracts\Collector;
use Tooling\Composer\ClassMap\Collectors\Provides\Fakeable;

class StateMachines implements Collector
{
    use Fakeable;

    /** @return \Illuminate\Support\Collection<int, class-string> */
    public function collect(Collection $classes): Collection
    {
        return $classes
            ->reject(fn (string $class) => str_ends_with($class, 'Test') || str_ends_with($class, 'TestCases'))
            ->reject(fn (string $class) => str_contains($class, 'Fixtures\\Tooling'))
            ->filter(fn (string $class) => rescue(
                fn () => (new ReflectionClass($class))->isEnum() && is_subclass_of($class, StateMachineable::class),
                false,
                false,
            ))
            ->values();
    }
}
