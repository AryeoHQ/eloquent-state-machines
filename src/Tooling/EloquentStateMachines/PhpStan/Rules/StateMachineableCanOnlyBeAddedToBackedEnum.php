<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Enum_;
use PHPStan\Analyser\Scope;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Tooling\PhpStan\Rules\Rule;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Enum_>
 */
#[NodeType(Enum_::class)]
class StateMachineableCanOnlyBeAddedToBackedEnum extends Rule
{
    public function shouldHandle(Node $node, Scope $scope): bool
    {
        return $this->inherits($node, StateMachineable::class)
            && $node->scalarType === null;
    }

    public function handle(Node $node, Scope $scope): void
    {
        $this->error(
            '[StateMachineable] can only be implemented on a backed [Enum].',
            $node->name->getStartLine(),
            'eloquentStateMachines.stateMachineableOnlyBackedEnum'
        );
    }
}
