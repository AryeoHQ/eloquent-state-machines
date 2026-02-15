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
use Tooling\EloquentStateMachines\PhpStan\Support\CaseTriggers;
use Tooling\PhpStan\Rules\Rule;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Enum_>
 */
#[NodeType(Enum_::class)]
class StateMachineableCannotDuplicateTransitionTriggerPerCase extends Rule
{
    private readonly ReflectionProvider $reflectionProvider;

    /** @var Collection<(int|string), object{case: EnumCase, triggers: CaseTriggers}&\stdClass> */
    private Collection $duplicates;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function prepare(Node $node, Scope $scope): void
    {
        $this->duplicates = collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof EnumCase)
            ->map(fn (EnumCase $case): object => (object) ['case' => $case, 'triggers' => $this->caseLookup($case)])
            ->filter(fn (object $item): bool => $item->triggers->hasDuplicates());
    }

    public function shouldHandle(Node $node, Scope $scope): bool
    {
        return $this->inherits($node, StateMachineable::class, $this->reflectionProvider)
            && $this->duplicates->isNotEmpty();
    }

    public function handle(Node $node, Scope $scope): void
    {
        $this->duplicates->each(function (object $item): void {
            $item->triggers->duplicates()->each(
                fn (string $trigger) => $this->error(
                    "[$trigger] duplicated: A trigger can only be used once per case.",
                    $item->case->name->getStartLine(),
                    'eloquentStateMachines.stateMachineableDuplicateTransitionTriggerPerCase'
                )
            );
        });
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
