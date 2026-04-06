<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use Illuminate\Queue\SerializesModels;
use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PHPStan\Analyser\Scope;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends \Tooling\PhpStan\Rules\Rule<Class_>
 */
#[NodeType(Class_::class)]
final class TriggerCannotUseSerializesModels extends \Tooling\PhpStan\Rules\Rule
{
    /**
     * @param  Class_  $node
     */
    public function shouldHandle(Node $node, Scope $scope): bool
    {
        return $node->namespacedName?->toString() === Trigger::class
            && $this->inherits($node, SerializesModels::class);
    }

    /**
     * @param  Class_  $node
     */
    public function handle(Node $node, Scope $scope): void
    {
        $this->error(
            '`Trigger` cannot use the `'.SerializesModels::class.'` trait. See README.md for details.',
            $this->findSerializesModelsTraitLine($node) ?? $node->getStartLine(),
            'eloquentStateMachines.triggerCannotUseSerializesModels'
        );
    }

    private function findSerializesModelsTraitLine(Class_ $node): null|int
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof TraitUse) {
                foreach ($stmt->traits as $trait) {
                    if ($trait instanceof FullyQualified && $trait->toString() === SerializesModels::class) {
                        return $stmt->getStartLine();
                    }
                }
            }
        }

        return null;
    }
}
