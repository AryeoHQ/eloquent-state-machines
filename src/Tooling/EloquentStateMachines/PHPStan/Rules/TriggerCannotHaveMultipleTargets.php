<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Tooling\PhpStan\Rules\Rule;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Class_>
 */
#[NodeType(Class_::class)]
class TriggerCannotHaveMultipleTargets extends Rule
{
    private readonly ReflectionProvider $reflectionProvider;

    /** @var \Illuminate\Support\Collection<int, Property> */
    private \Illuminate\Support\Collection $targetProperties;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function prepare(Node $node, Scope $scope): void
    {
        $this->targetProperties = collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof Property)
            ->filter(fn (Property $property): bool => $this->hasAttribute($property, Target::class));
    }

    public function shouldHandle(Node $node, Scope $scope): bool
    {
        if ($node->isAbstract()) {
            return false;
        }

        return $this->inherits($node, Trigger::class, $this->reflectionProvider)
            && $this->targetProperties->count() > 1;
    }

    public function handle(Node $node, Scope $scope): void
    {
        $this->targetProperties->each(
            fn (Property $property) => $this->error(
                'Only one property with #[Target] attribute permitted.',
                $this->getPropertyLine($property),
                'eloquentStateMachines.triggerCannotHaveDuplicateTargetProperties'
            )
        );
    }

    private function getPropertyLine(Property $property): int
    {
        $propertyName = data_get($property, 'props.0.name');

        return $propertyName?->getStartLine() ?? $property->getStartLine();
    }
}
