<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;

/**
 * @implements Rule<Class_>
 */
class TriggerCannotHaveMultipleTargets implements Rule
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

        return $this->hasMultipleTargetProperties($node);
    }

    private function extendsTrigger(Class_ $node): bool
    {
        if ($node->extends === null) {
            return false;
        }

        $parentClassName = $node->extends->toString();

        return $parentClassName === Trigger::class || str_ends_with($parentClassName, 'Trigger');
    }

    private function hasMultipleTargetProperties(Class_ $node): bool
    {
        return $this->getTargetProperties($node)->count() > 1;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Property>
     */
    private function getTargetProperties(Class_ $node): \Illuminate\Support\Collection
    {
        return collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof Property)
            ->filter(fn (Property $property): bool => $this->hasTargetAttribute($property));
    }

    private function hasTargetAttribute(Property $property): bool
    {
        return collect($property->attrGroups)
            ->flatMap(fn (AttributeGroup $attrGroup) => $attrGroup->attrs)
            ->contains(fn (Attribute $attr): bool => $attr->name->toString() === Target::class);
    }

    /**
     * @return array<array-key, IdentifierRuleError>
     */
    private function buildErrors(Class_ $node): array
    {
        $className = $node->namespacedName?->toString() ?? $node->name?->toString() ?? 'Unknown';

        return $this->getTargetProperties($node)
            ->map(fn (Property $property): IdentifierRuleError => RuleErrorBuilder::message(
                'Only one property with #[Target] attribute permitted.'
            )
                ->identifier('eloquentStateMachines.triggerCannotHaveDuplicateTargetProperties')
                ->line($this->getPropertyLine($property))
                ->build()
            )
            ->all();
    }

    private function getPropertyLine(Property $property): int
    {
        $propertyName = data_get($property, 'props.0.name');

        return $propertyName?->getStartLine() ?? $property->getStartLine();
    }
}
