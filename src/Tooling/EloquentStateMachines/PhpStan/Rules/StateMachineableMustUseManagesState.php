<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Enum_;
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
class StateMachineableMustUseManagesState extends Rule
{
    private readonly ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function shouldHandle(Node $node, Scope $scope): bool
    {
        return $this->inherits($node, StateMachineable::class, $this->reflectionProvider)
            && $this->doesNotInherit($node, ManagesState::class, $this->reflectionProvider);
    }

    public function handle(Node $node, Scope $scope): void
    {
        $this->error(
            '[StateMachineable] must use the [ManagesState] trait.',
            $node->name->getStartLine(),
            'eloquentStateMachines.stateMachineableMustUseManagesState'
        );
    }
}
