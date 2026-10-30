<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PHPStan\Analyser\Scope;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Tooling\PhpStan\Rules\Rule;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Enum_>
 */
#[NodeType(Enum_::class)]
class StateMachineableStationaryTransitionCannotDefineEvents extends Rule
{
    /** @var Collection<int, object{case: EnumCase, trigger: string}&\stdClass> */
    private Collection $stationary;

    public function prepare(Node $node, Scope $scope): void
    {
        $enum = $node->namespacedName?->toString() ?? '';

        $this->stationary = collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof EnumCase)
            ->flatMap(fn (EnumCase $case): Collection => $this->stationaryWithEvents($case, $enum)->map(
                fn (string $trigger): object => (object) ['case' => $case, 'trigger' => $trigger]
            ))
            ->values();
    }

    public function shouldHandle(Node $node, Scope $scope): bool
    {
        return $this->inherits($node, StateMachineable::class)
            && $this->stationary->isNotEmpty();
    }

    public function handle(Node $node, Scope $scope): void
    {
        $this->stationary->each(
            fn (object $item) => $this->error(
                "[{$item->trigger}] stationary: A transition to its own case cannot define events.",
                $item->case->name->getStartLine(),
                'eloquentStateMachines.stateMachineableStationaryTransitionCannotDefineEvents'
            )
        );
    }

    /**
     * @return Collection<int, string>
     */
    private function stationaryWithEvents(EnumCase $case, string $enum): Collection
    {
        return collect($case->attrGroups)
            ->flatMap(fn (AttributeGroup $attrGroup) => $attrGroup->attrs)
            ->filter(fn (Attribute $attr): bool => $attr->name->toString() === Transition::class)
            ->map(fn (Attribute $attr): Collection => collect($attr->args)->mapWithKeys(
                fn (Arg $arg, int $index): array => [$arg->name?->toString() ?? ['to', 'using', 'events'][$index] ?? '' => $arg->value]
            ))
            ->filter(fn (Collection $args): bool => $this->declaresEvents($args->get('events')) && $this->targets($args->get('to'), $case, $enum))
            ->map(fn (Collection $args): string => $args->get('using') instanceof ClassConstFetch && $args->get('using')->class instanceof Name ? $args->get('using')->class->toString() : '')
            ->values();
    }

    private function declaresEvents(null|Expr $events): bool
    {
        return $events !== null
            && ! ($events instanceof ConstFetch && $events->name->toLowerString() === 'null');
    }

    private function targets(null|Expr $to, EnumCase $case, string $enum): bool
    {
        return $to instanceof ClassConstFetch
            && $to->class instanceof Name
            && in_array($to->class->toLowerString(), ['self', 'static', strtolower($enum)], true)
            && $to->name instanceof Identifier
            && $to->name->toString() === $case->name->toString();
    }
}
