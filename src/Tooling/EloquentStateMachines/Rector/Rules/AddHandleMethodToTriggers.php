<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\Rector\Rules;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Tooling\Rector\Rules\Definitions\Attributes\Definition;
use Tooling\Rector\Rules\Rule;
use Tooling\Rector\Rules\Samples\Attributes\Sample;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Class_>
 */
#[Definition('Add handle() method stub to Trigger classes')]
#[NodeType(Class_::class)]
#[Sample('state-machines.rector.rules.samples')]
final class AddHandleMethodToTriggers extends Rule
{
    public function shouldHandle(Node $node): bool
    {
        if (! $node instanceof Class_) {
            return false;
        }

        if ($node->isAbstract()) {
            return false;
        }

        return $this->inherits($node, Trigger::class)
            && ! $this->hasHandleMethod($node);
    }

    public function handle(Node $node): Node
    {
        $handleMethod = new ClassMethod('handle', [
            'flags' => Modifiers::PUBLIC,
            'returnType' => new Node\Identifier('void'),
            'stmts' => [],
        ]);

        $node->stmts[] = $handleMethod;

        return $node;
    }

    private function hasHandleMethod(Class_ $node): bool
    {
        return collect($node->stmts)
            ->filter(fn (Node\Stmt $stmt): bool => $stmt instanceof ClassMethod)
            ->contains(fn (ClassMethod $method): bool => $method->name->toString() === 'handle');
    }
}
