<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\TraitUse;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tooling\PhpStan\Rules\Rule;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Enum_>
 */
#[NodeType(Enum_::class)]
class ManagesStateCanOnlyBeAddedToStateMachineable extends Rule
{
    private readonly ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function shouldHandle(Node $node, Scope $scope): bool
    {
        return $this->inherits($node, ManagesState::class, $this->reflectionProvider)
            && $this->doesNotInherit($node, StateMachineable::class, $this->reflectionProvider);
    }

    public function handle(Node $node, Scope $scope): void
    {
        $this->error(
            '[ManagesState] trait can only be used on implementations of [StateMachineable].',
            $this->findManagesStateTrait($node)->getStartLine(),
            'eloquentStateMachines.managesStateOnlyOnStateMachineable'
        );
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
}
