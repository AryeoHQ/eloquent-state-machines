<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Tooling\PhpStan\Rules\Rule;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Class_>
 */
#[NodeType(Class_::class)]
class TriggersMustDefineHandle extends Rule
{
    public function shouldHandle(Node $node, Scope $scope): bool
    {
        if ($node->isAbstract()) {
            return false;
        }

        return $this->inherits($node, Trigger::class)
            && ! $this->hasMethod($node, 'handle');
    }

    public function handle(Node $node, Scope $scope): void
    {
        $this->error(
            'Triggers must define a handle() method.',
            $node->name->getStartLine(),
            'eloquentStateMachines.triggerMustDefineHandle'
        );
    }
}
