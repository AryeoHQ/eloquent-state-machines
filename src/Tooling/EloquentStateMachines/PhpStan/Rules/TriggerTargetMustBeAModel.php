<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Tooling\PhpStan\Rules\Rule;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Class_>
 */
#[NodeType(Class_::class)]
class TriggerTargetMustBeAModel extends Rule
{
    private readonly ReflectionProvider $reflectionProvider;

    /** @var Collection<int, Property> */
    private Collection $invalidTargets;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function prepare(Node $node, Scope $scope): void
    {
        $this->invalidTargets = collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof Property)
            ->filter(fn (Property $property): bool => $this->hasAttribute($property, Target::class))
            ->filter(fn (Property $property): bool => ! $this->isValidModelType($property));
    }

    public function shouldHandle(Node $node, Scope $scope): bool
    {
        if ($node->isAbstract()) {
            return false;
        }

        return $this->inherits($node, Trigger::class, $this->reflectionProvider)
            && $this->invalidTargets->isNotEmpty();
    }

    public function handle(Node $node, Scope $scope): void
    {
        $this->invalidTargets->each(
            fn (Property $property) => $this->error(
                "Property with #[Target] attribute must be a [Model], [{$property->type->toString()}] given.",
                $this->getPropertyLine($property),
                'eloquentStateMachines.triggerTargetMustBeModel'
            )
        );
    }

    private function isValidModelType(Property $property): bool
    {
        if ($property->type === null) {
            return true;
        }

        $typeName = $property->type->toString();
        $propertyType = new ObjectType($typeName);
        $modelType = new ObjectType(Model::class);

        return $modelType->isSuperTypeOf($propertyType)->yes();
    }

    private function getPropertyLine(Property $property): int
    {
        return data_get($property, 'props.0.name')?->getStartLine() ?? $property->getStartLine();
    }
}
