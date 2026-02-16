<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Tooling\PhpStan\Rules\Rule;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Enum_>
 */
#[NodeType(Enum_::class)]
class TransitionUsingMustBeATrigger extends Rule
{
    private readonly ReflectionProvider $reflectionProvider;

    /** @var Collection<int, object{case: EnumCase, using: string}&\stdClass> */
    private Collection $invalidUsings;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function prepare(Node $node, Scope $scope): void
    {
        $this->invalidUsings = collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof EnumCase)
            ->flatMap(fn (EnumCase $case) => $this->extractUsings($case)->map(
                fn (string $using): object => (object) ['case' => $case, 'using' => $using]
            ))
            ->filter(fn (object $item): bool => ! $this->isTrigger($item->using));
    }

    public function shouldHandle(Node $node, Scope $scope): bool
    {
        return $this->inherits($node, StateMachineable::class, $this->reflectionProvider)
            && $this->invalidUsings->isNotEmpty();
    }

    public function handle(Node $node, Scope $scope): void
    {
        $this->invalidUsings->each(
            fn (object $item) => $this->error(
                "[{$item->using}] is not a Trigger. The #[Transition] using parameter must reference a class that extends Trigger.",
                $item->case->name->getStartLine(),
                'eloquentStateMachines.transitionUsingMustBeATrigger'
            )
        );
    }

    /**
     * @return Collection<int, string>
     */
    private function extractUsings(EnumCase $case): Collection
    {
        /** @phpstan-ignore return.type */
        return collect($case->attrGroups)
            ->flatMap(fn (AttributeGroup $attrGroup) => $attrGroup->attrs)
            ->filter(fn (Attribute $attr): bool => $attr->name->toString() === Transition::class)
            ->flatMap(fn (Attribute $attr) => $attr->args)
            ->filter(fn (Arg $arg): bool => $arg->name !== null && $arg->name->toString() === 'using')
            ->map(fn (Arg $arg) => $arg->value)
            ->filter(fn (Node\Expr $value): bool => $value instanceof ClassConstFetch)
            ->map(fn (ClassConstFetch $value): string => $value->class->toString());
    }

    private function isTrigger(string $class): bool
    {
        if (! $this->reflectionProvider->hasClass($class)) {
            return false;
        }

        $reflection = $this->reflectionProvider->getClass($class);

        return $this->inherits($reflection, Trigger::class, $this->reflectionProvider);
    }
}
