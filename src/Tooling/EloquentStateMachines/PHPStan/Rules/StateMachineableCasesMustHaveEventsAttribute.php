<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;

/**
 * @implements Rule<Enum_>
 */
class StateMachineableCasesMustHaveEventsAttribute implements Rule
{
    public function getNodeType(): string
    {
        return Enum_::class;
    }

    /**
     * @param  Enum_  $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        return $this->passes($node) ? [] : $this->buildErrors($node);
    }

    private function passes(Enum_ $node): bool
    {
        return ! $this->violated($node);
    }

    private function violated(Enum_ $node): bool
    {
        $className = $node->namespacedName?->toString() ?? '';
        if (str($className)->is('Tests\\*Fixtures\\*')) {
            return false;
        }

        if (! $this->implementsStateMachineable($node)) {
            return false;
        }

        return $this->hasCasesWithoutEventsAttribute($node);
    }

    private function implementsStateMachineable(Enum_ $node): bool
    {
        return collect($node->implements)
            ->map(fn (Node\Name $implement): string => $implement->toString())
            ->contains(StateMachineable::class);
    }

    private function hasCasesWithoutEventsAttribute(Enum_ $node): bool
    {
        return collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof EnumCase)
            ->contains(fn (EnumCase $case): bool => ! $this->hasEventsAttribute($case));
    }

    private function hasEventsAttribute(EnumCase $case): bool
    {
        return collect($case->attrGroups)
            ->flatMap(fn (AttributeGroup $attrGroup) => $attrGroup->attrs)
            ->contains(fn (Attribute $attr): bool => $attr->name->toString() === Events::class);
    }

    /**
     * @return array<array-key, IdentifierRuleError>
     */
    private function buildErrors(Enum_ $node): array
    {
        return collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof EnumCase)
            ->filter(fn (EnumCase $case): bool => ! $this->hasEventsAttribute($case))
            ->map(fn (EnumCase $case): IdentifierRuleError => RuleErrorBuilder::message(
                '#[Events] attribute required on [StateMachineable] cases.'
            )
                ->identifier('eloquentStateMachines.stateMachineableCasesMustHaveEventsAttribute')
                ->line($case->name->getStartLine())
                ->build()
            )
            ->all();
    }
}
