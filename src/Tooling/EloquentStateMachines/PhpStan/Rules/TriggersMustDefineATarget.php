<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Tooling\PhpStan\Rules\Rule;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Class_>
 */
#[NodeType(Class_::class)]
class TriggersMustDefineATarget extends Rule
{
    public function shouldHandle(Node $node, Scope $scope): bool
    {
        if ($node->isAbstract()) {
            return false;
        }

        return $this->inherits($node, Trigger::class)
            && ! $this->hasTargetAttribute($node);
    }

    public function handle(Node $node, Scope $scope): void
    {
        $this->error(
            'Triggers must define a property with the #[Target] attribute.',
            $node->name->getStartLine(),
            'eloquentStateMachines.triggerMustDefineTarget'
        );
    }

    private function hasTargetAttribute(Class_ $node): bool
    {
        return collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof Property)
            ->contains(fn (Property $property): bool => $this->hasAttribute($property, Target::class));
    }
}
