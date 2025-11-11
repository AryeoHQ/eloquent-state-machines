<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Tooling\EloquentStateMachines\PHPStan\Support\CaseTriggers;

/**
 * @implements Rule<Enum_>
 */
class StateMachineableCannotDuplicateTransitionTriggerPerCase implements Rule
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

        return $this->hasDuplicateTransitionTriggers($node);
    }

    private function implementsStateMachineable(Enum_ $node): bool
    {
        return collect($node->implements)
            ->map(fn (Node\Name $implement): string => $implement->toString())
            ->contains(StateMachineable::class);
    }

    private function hasDuplicateTransitionTriggers(Enum_ $node): bool
    {
        return collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof EnumCase)
            ->contains(fn (EnumCase $case): bool => $this->caseLookup($case)->hasDuplicates());
    }

    /**
     * @return array<array-key, IdentifierRuleError>
     */
    private function buildErrors(Enum_ $node): array
    {
        return collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof EnumCase)
            ->flatMap(fn (EnumCase $case): array => $this->buildErrorsForCase($case))
            ->all();
    }

    /**
     * @return array<array-key, IdentifierRuleError>
     */
    private function buildErrorsForCase(EnumCase $case): array
    {
        $lookup = $this->caseLookup($case);

        if (! $lookup->hasDuplicates()) {
            return [];
        }

        return $lookup->duplicates()
            ->map(fn (string $trigger): IdentifierRuleError => RuleErrorBuilder::message(
                "[$trigger] duplicated: A trigger can only be used once per case."
            )
                ->identifier('eloquentStateMachines.stateMachineableDuplicateTransitionTriggerPerCase')
                ->line($case->name->getStartLine())
                ->build()
            )
            ->all();
    }

    private function caseLookup(EnumCase $case): CaseTriggers
    {
        $transitions = collect($case->attrGroups)
            ->flatMap(fn (AttributeGroup $attrGroup) => $attrGroup->attrs)
            ->filter(fn (Attribute $attr): bool => $attr->name->toString() === Transition::class)
            ->flatMap(fn (Attribute $attr) => $attr->args)
            ->filter(fn (Arg $arg): bool => $arg->name !== null && $arg->name->toString() === 'using')
            ->map(fn (Arg $arg) => $arg->value)
            ->filter(fn (Node\Expr $value): bool => $value instanceof ClassConstFetch)
            ->map(fn (ClassConstFetch $value): string => $value->class->toString())
            ->all();

        return new CaseTriggers($transitions);
    }
}
