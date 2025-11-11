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
class ManagesStateCanOnlyBeAddedToStateMachineable implements Rule
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

        $trait = $this->findManagesStateTrait($node);

        if ($trait === null) {
            return false;
        }

        return $this->doesNotImplementStateMachineable($node);
    }

    private function findManagesStateTrait(Enum_ $node): null|TraitUse
    {
        return collect($node->stmts)
            ->filter(fn ($stmt): bool => $stmt instanceof TraitUse)
            ->first(function (TraitUse $stmt): bool {
                return collect($stmt->traits)
                    ->map(fn ($trait) => $trait->toString())
                    ->contains(ManagesState::class);
            });
    }

    private function doesNotImplementStateMachineable(Enum_ $node): bool
    {
        return ! $this->implementsStateMachineable($node);
    }

    private function implementsStateMachineable(Enum_ $node): bool
    {
        return collect($node->implements)
            ->map(fn ($implement): string => $implement->toString())
            ->contains(StateMachineable::class);
    }

    /**
     * @return array<array-key, IdentifierRuleError>
     */
    private function buildError(Enum_ $node): array
    {
        return [
            RuleErrorBuilder::message('[ManagesState] trait can only be used on implementations of [StateMachineable].')
                ->identifier('eloquentStateMachines.managesStateOnlyOnStateMachineable')
                ->line($this->findManagesStateTrait($node)->getStartLine())->build(),
        ];
    }
}
