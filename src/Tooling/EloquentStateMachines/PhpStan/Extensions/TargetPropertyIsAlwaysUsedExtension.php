<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Extensions;

use PHPStan\Reflection\ExtendedPropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;
use PHPStan\Type\ObjectType;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;

/**
 * Tells PHPStan that properties annotated with #[Target] on Trigger
 * subclasses are always read, written, and initialized.
 *
 * The Trigger base class accesses these properties dynamically through
 * a hooked $model property: $this->{$this->target()}, which PHPStan
 * cannot trace statically.
 */
final class TargetPropertyIsAlwaysUsedExtension implements ReadWritePropertiesExtension
{
    public function isAlwaysRead(ExtendedPropertyReflection $property, string $propertyName): bool
    {
        return $this->isTriggerTargetProperty($property);
    }

    public function isAlwaysWritten(ExtendedPropertyReflection $property, string $propertyName): bool
    {
        return $this->isTriggerTargetProperty($property);
    }

    public function isInitialized(ExtendedPropertyReflection $property, string $propertyName): bool
    {
        return $this->isTriggerTargetProperty($property);
    }

    private function isTriggerTargetProperty(ExtendedPropertyReflection $property): bool
    {
        $declaringClass = $property->getDeclaringClass();
        $triggerType = new ObjectType(Trigger::class);

        if (! $triggerType->isSuperTypeOf(new ObjectType($declaringClass->getName()))->yes()) {
            return false;
        }

        foreach ($property->getAttributes() as $attribute) {
            if ($attribute->getName() === Target::class) {
                return true;
            }
        }

        return false;
    }
}
