<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\Rector\Rules;

use Illuminate\Queue\SerializesModels;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tooling\Rector\Rules\Definitions\Attributes\Definition;
use Tooling\Rector\Rules\Samples\Attributes\Sample;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends \Tooling\Rector\Rules\Rule<\PhpParser\Node\Stmt\Class_>
 */
#[Definition('Remove SerializesModels trait from Trigger')]
#[NodeType(Class_::class)]
#[Sample('state-machines.rector.rules.samples')]
class TriggerCannotUseSerializesModels extends \Tooling\Rector\Rules\Rule
{
    public function shouldHandle(Node $node): bool
    {
        return $this->isName($node, Trigger::class)
            && $this->inherits($node, SerializesModels::class);
    }

    public function handle(Node $node): null|Node
    {
        return $this->removeTrait($node, SerializesModels::class);
    }
}
