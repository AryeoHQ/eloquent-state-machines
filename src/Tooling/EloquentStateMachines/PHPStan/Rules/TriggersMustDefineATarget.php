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
class TriggersMustDefineATarget implements Rule
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
        return $this->passes($node) ? [] : $this->buildError($node);
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

        return ! $this->hasTargetAttribute($node);
    }

    private function extendsTrigger(Class_ $node): bool
    {
        if ($node->extends === null) {
            return false;
        }

        $parentClassName = $node->extends->toString();

        return $parentClassName === Trigger::class || str_ends_with($parentClassName, 'Trigger');
    }

    private function hasTargetAttribute(Class_ $node): bool
    {
        return collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof Property)
            ->flatMap(fn (Property $property) => $property->attrGroups)
            ->flatMap(fn (AttributeGroup $attrGroup) => $attrGroup->attrs)
            ->contains(fn (Attribute $attr): bool => $attr->name->toString() === Target::class);
    }

    /**
     * @return array<array-key, IdentifierRuleError>
     */
    private function buildError(Class_ $node): array
    {
        $className = $node->namespacedName?->toString() ?? $node->name?->toString() ?? 'Unknown';

        return [
            RuleErrorBuilder::message(
                'Triggers must define a property with the #[Target] attribute.'
            )
                ->identifier('eloquentStateMachines.triggerMustDefineTarget')
                ->line($node->name->getStartLine())
                ->build(),
        ];
    }
}
