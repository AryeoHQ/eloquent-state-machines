<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Tooling\PhpStan\Rules\Rule;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Enum_>
 */
#[NodeType(Enum_::class)]
class StateMachineableCasesMustHaveEventsAttribute extends Rule
{
    private readonly ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function shouldHandle(Node $node, Scope $scope): bool
    {
        return $this->inherits($node, StateMachineable::class, $this->reflectionProvider);
    }

    public function handle(Node $node, Scope $scope): void
    {
        collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof EnumCase)
            ->filter(fn (EnumCase $case): bool => ! $this->hasAttribute($case, Events::class))
            ->each(fn (EnumCase $case) => $this->error(
                '#[Events] attribute required on [StateMachineable] cases.',
                $case->name->getStartLine(),
                'eloquentStateMachines.stateMachineableCasesMustHaveEventsAttribute'
            ));
    }
}
