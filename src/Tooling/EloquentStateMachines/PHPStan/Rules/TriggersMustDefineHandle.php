<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Tooling\PhpStan\Rules\Rule;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Class_>
 */
#[NodeType(Class_::class)]
class TriggersMustDefineHandle extends Rule
{
    private readonly ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function shouldHandle(Node $node, Scope $scope): bool
    {
        if ($node->isAbstract()) {
            return false;
        }

        return $this->inherits($node, Trigger::class, $this->reflectionProvider)
            && ! $this->hasHandleMethod($node);
    }

    public function handle(Node $node, Scope $scope): void
    {
        $this->error(
            'Triggers must define a handle() method.',
            $node->name->getStartLine(),
            'eloquentStateMachines.triggerMustDefineHandle'
        );
    }

    private function hasHandleMethod(Class_ $node): bool
    {
        return collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof ClassMethod)
            ->contains(fn (ClassMethod $method): bool => $method->name->toString() === 'handle');
    }
}
