<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PHPStan\Analyser\Scope;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Tooling\PhpStan\Rules\Rule;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Enum_>
 */
#[NodeType(Enum_::class)]
class StateMachineableCannotDuplicateEvents extends Rule
{
    /** @var Collection<int, object{case: EnumCase, event: non-empty-string}&\stdClass> */
    private Collection $duplicates;

    public function prepare(Node $node, Scope $scope): void
    {
        $declarations = collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof EnumCase)
            ->flatMap(fn (EnumCase $case): Collection => $this->events($case)->map(
                fn (string $event): object => (object) ['case' => $case, 'event' => $event]
            ))
            ->values();

        $this->duplicates = $declarations
            ->filter(fn (object $item, int $index): bool => $declarations->take($index)->contains('event', $item->event))
            ->values();
    }

    public function shouldHandle(Node $node, Scope $scope): bool
    {
        return $this->inherits($node, StateMachineable::class)
            && $this->duplicates->isNotEmpty();
    }

    public function handle(Node $node, Scope $scope): void
    {
        $this->duplicates->each(
            fn (object $item) => $this->error(
                "[{$item->event}] duplicated: An event can only be declared once per state machine.",
                $item->case->name->getStartLine(),
                'eloquentStateMachines.stateMachineableCannotDuplicateEvents'
            )
        );
    }

    /**
     * @return Collection<int, non-empty-string>
     */
    private function events(EnumCase $case): Collection
    {
        $attributes = collect($case->attrGroups)->flatMap(fn (AttributeGroup $attrGroup) => $attrGroup->attrs);

        return collect([
            ...$attributes
                ->filter(fn (Attribute $attr): bool => $attr->name->toString() === Events::class)
                ->map(fn (Attribute $attr): array => $attr->args),
            ...$attributes
                ->filter(fn (Attribute $attr): bool => $attr->name->toString() === Transition::class)
                ->map(fn (Attribute $attr): mixed => collect($attr->args)->first(fn (Arg $arg, int $index): bool => $arg->name?->toString() === 'events' || ($arg->name === null && $index === 2))?->value)
                ->filter(fn (mixed $value): bool => $value instanceof New_ && $value->class instanceof Name && $value->class->toString() === Events::class)
                ->map(fn (New_ $new): array => $new->args),
        ])
            ->flatMap(fn (array $args): Collection => collect($args)
                ->map(fn (Node $arg): mixed => $arg instanceof Arg ? $arg->value : null)
                ->filter(fn (mixed $value): bool => $value instanceof ClassConstFetch && $value->class instanceof Name && $value->name instanceof Identifier && $value->name->toLowerString() === 'class')
                ->map(fn (ClassConstFetch $fetch): string => $fetch->class->toString()))
            ->values();
    }
}
