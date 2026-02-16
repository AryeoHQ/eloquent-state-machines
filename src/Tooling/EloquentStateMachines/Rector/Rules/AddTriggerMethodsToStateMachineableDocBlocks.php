<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\Rector\Rules;

use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\Stmt\Enum_;
use PHPStan\PhpDocParser\Ast\PhpDoc\MethodTagValueNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionEnumUnitCase;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachine;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Tooling\Nodes\Method;
use Tooling\Rector\Rules\Definitions\Attributes\Definition;
use Tooling\Rector\Rules\Rule;
use Tooling\Rector\Rules\Samples\Attributes\Sample;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Enum_>
 */
#[Definition('Add trigger method doc blocks to StateMachineable enums')]
#[NodeType(Enum_::class)]
#[Sample('state-machines.rector.rules.samples')]
final class AddTriggerMethodsToStateMachineableDocBlocks extends Rule
{
    public PhpDocInfoFactory $phpDocInfoFactory;

    public DocBlockUpdater $docBlockUpdater;

    public function __construct(PhpDocInfoFactory $phpDocInfoFactory, DocBlockUpdater $docBlockUpdater)
    {
        $this->phpDocInfoFactory = $phpDocInfoFactory;
        $this->docBlockUpdater = $docBlockUpdater;
    }

    public function shouldHandle(Node $node): bool
    {
        if (! $node instanceof Enum_) {
            return false;
        }

        return $this->inherits($node, ManagesState::class)
            && $this->inherits($node, StateMachine::class);
    }

    public function handle(Node $node): Node
    {
        $docBlock = $this->prepareDocBlock($node);

        $this->methods($node)->map(
            fn (Method $method) => $method->alias([
                'name' => str(class_basename($method->of))->camel()->toString(),
                'type' => $method->of,
            ])
        )->map->toDocBlockTag()->each(
            fn ($method): null => $docBlock->addTagValueNode($method)
        );

        $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);

        return $node;
    }

    private function prepareDocBlock(Enum_ $node): PhpDocInfo
    {
        return tap(
            $this->phpDocInfoFactory->createFromNodeOrEmpty($node),
            fn (PhpDocInfo $docBlock): bool => $docBlock->removeByType(MethodTagValueNode::class)
        );
    }

    /**
     * @return Collection<array-key, StateMachineable>
     */
    private function cases(Enum_ $node): Collection
    {
        return with(
            new ReflectionEnum($node->namespacedName->name),
            fn (ReflectionEnum $enumReflection): Collection => collect($enumReflection->getCases())->map(
                /** @return StateMachineable */
                fn (ReflectionEnumBackedCase|ReflectionEnumUnitCase $caseReflection) => $enumReflection->getName()::{$caseReflection->getName()}
            )
        );
    }

    /**
     * @return Collection<array-key, Transition>
     */
    private function transitions(Enum_ $node): Collection
    {
        return $this->cases($node)->flatMap(
            fn (StateMachineable $case) => $case->transitions()
        )->unique('using')->filter(
            fn (Transition $transition): bool => is_a($transition->using, Trigger::class, true)
        );
    }

    /**
     * @return Collection<array-key, Method>
     */
    private function methods(Enum_ $node): Collection
    {
        return $this->transitions($node)->map(
            fn (Transition $transition): Method => new Method(of: $transition->using, name: '__construct')
        );
    }
}
