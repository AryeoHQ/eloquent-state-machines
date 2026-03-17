<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Extensions;

use Illuminate\Database\Eloquent\Model;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Type\ObjectType;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;

/**
 * Teaches PHPStan that enums implementing StateMachineable have $enum and $model
 * properties. These properties live on the StateMachine proxy, but Larastan resolves
 * cast model attributes as the raw enum type rather than the proxy wrapper.
 */
final class StateMachineablePropertiesExtension implements PropertiesClassReflectionExtension
{
    private const PROPERTIES = ['enum', 'model'];

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        if (! in_array($propertyName, self::PROPERTIES, true)) {
            return false;
        }

        if (! $classReflection->isEnum()) {
            return false;
        }

        return $classReflection->implementsInterface(StateMachineable::class);
    }

    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        $type = match ($propertyName) {
            'enum' => new ObjectType($classReflection->getName()),
            'model' => new ObjectType(Model::class),
            default => new ObjectType($classReflection->getName()),
        };

        return new StateMachineablePropertyReflection($classReflection, $type);
    }
}
