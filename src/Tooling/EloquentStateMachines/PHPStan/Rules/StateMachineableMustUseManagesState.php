<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\TraitUse;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;

/**
 * @implements Rule<Enum_>
 */
class StateMachineableMustUseManagesState implements Rule
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
        return $this->passes($node) ? [] : $this->buildError($node);
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

        return $this->doesNotUseManagesStateTrait($node);
    }

    private function implementsStateMachineable(Enum_ $node): bool
    {
        return collect($node->implements)
            ->map(fn (Node\Name $implement): string => $implement->toString())
            ->contains(StateMachineable::class);
    }

    private function doesNotUseManagesStateTrait(Enum_ $node): bool
    {
        return ! $this->usesManagesStateTrait($node);
    }

    private function usesManagesStateTrait(Enum_ $node): bool
    {
        return collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof TraitUse)
            ->flatMap(fn (TraitUse $stmt) => $stmt->traits)
            ->map(fn (Node\Name $trait): string => $trait->toString())
            ->contains(ManagesState::class);
    }

    /**
     * @return array<array-key, IdentifierRuleError>
     */
    private function buildError(Enum_ $node): array
    {
        return [
            RuleErrorBuilder::message(
                '[StateMachineable] must use the [ManagesState] trait.'
            )
                ->identifier('eloquentStateMachines.stateMachineableMustUseManagesState')
                ->line($node->name->getStartLine())
                ->build(),
        ];
    }
}
