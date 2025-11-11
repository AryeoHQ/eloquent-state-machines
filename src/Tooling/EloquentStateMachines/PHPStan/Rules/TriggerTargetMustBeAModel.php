<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PHPStan\Rules;

use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;

/**
 * @implements Rule<Class_>
 */
class TriggerTargetMustBeAModel implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param  Class_  $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        return $this->passes($node) ? [] : $this->buildErrors($node);
    }

    private function passes(Class_ $node): bool
    {
        return ! $this->violated($node);
    }

    private function violated(Class_ $node): bool
    {
        $className = $node->namespacedName?->toString() ?? '';
        if (str($className)->is('Tests\\*Fixtures\\*')) {
            return false;
        }

        if ($node->isAbstract()) {
            return false;
        }

        if (! $this->extendsTrigger($node)) {
            return false;
        }

        return $this->hasInvalidTargetProperties($node);
    }

    private function extendsTrigger(Class_ $node): bool
    {
        if ($node->extends === null) {
            return false;
        }

        $parentClassName = $node->extends->toString();

        return $parentClassName === Trigger::class || str_ends_with($parentClassName, 'Trigger');
    }

    private function hasInvalidTargetProperties(Class_ $node): bool
    {
        return $this->getInvalidTargetProperties($node)->isNotEmpty();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Property>
     */
    private function getInvalidTargetProperties(Class_ $node): \Illuminate\Support\Collection
    {
        return collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof Property)
            ->filter(fn (Property $property): bool => $this->hasTargetAttribute($property))
            ->filter(fn (Property $property): bool => ! $this->isValidModelType($property));
    }

    private function hasTargetAttribute(Property $property): bool
    {
        return collect($property->attrGroups)
            ->flatMap(fn (AttributeGroup $attrGroup) => $attrGroup->attrs)
            ->contains(fn (Attribute $attr): bool => $attr->name->toString() === Target::class || str_ends_with($attr->name->toString(), 'Target')
            );
    }

    private function isValidModelType(Property $property): bool
    {
        if ($property->type === null) {
            return true;
        }

        $typeName = $property->type->toString();
        $propertyType = new ObjectType($typeName);
        $modelType = new ObjectType(Model::class);

        return $modelType->isSuperTypeOf($propertyType)->yes();
    }

    /**
     * @return array<array-key, IdentifierRuleError>
     */
    private function buildErrors(Class_ $node): array
    {
        return $this->getInvalidTargetProperties($node)
            ->map(fn (Property $property): IdentifierRuleError => RuleErrorBuilder::message(
                "Property with #[Target] attribute must be a [Model], [{$property->type->toString()}] given."
            )
                ->identifier('eloquentStateMachines.triggerTargetMustBeModel')
                ->line($this->getPropertyLine($property))
                ->build()
            )
            ->all();
    }

    private function getPropertyLine(Property $property): int
    {
        return data_get($property, 'props.0.name')?->getStartLine() ?? $property->getStartLine();
    }
}
