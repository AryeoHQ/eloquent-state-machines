<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Enum_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;

/**
 * @implements Rule<Enum_>
 */
class StateMachineableCanOnlyBeAddedToBackedEnum implements Rule
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

        return $this->isNotBackedEnum($node);
    }

    private function implementsStateMachineable(Enum_ $node): bool
    {
        return collect($node->implements)
            ->map(fn (Node\Name $implement): string => $implement->toString())
            ->contains(StateMachineable::class);
    }

    private function isNotBackedEnum(Enum_ $node): bool
    {
        return $node->scalarType === null;
    }

    /**
     * @return array<array-key, IdentifierRuleError>
     */
    private function buildError(Enum_ $node): array
    {
        return [
            RuleErrorBuilder::message(
                '[StateMachineable] can only be implemented on a backed [Enum].'
            )
                ->identifier('eloquentStateMachines.stateMachineableOnlyBackedEnum')
                ->line($node->name->getStartLine())
                ->build(),
        ];
    }
}
