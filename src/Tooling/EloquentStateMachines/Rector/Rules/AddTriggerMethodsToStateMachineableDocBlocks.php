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
use Rector\Rector\AbstractRector;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionEnumUnitCase;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachine;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Tooling\Rector\Support\Nodes\Method;

final class AddTriggerMethodsToStateMachineableDocBlocks extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Enum_::class];
    }

    public function __construct(public PhpDocInfoFactory $phpDocInfoFactory, public DocBlockUpdater $docBlockUpdater)
    {
        $this->phpDocInfoFactory = $phpDocInfoFactory;
        $this->docBlockUpdater = $docBlockUpdater;
    }

    public function refactor(Node $node): null|Node
    {
        assert($node instanceof Enum_);

        if ($this->shouldNotRun($node)) {
            return null;
        }

        $docBlock = $this->prepareDocBlock($node);

        /** @phpstan-ignore-next-line Higher order proxy confuses PHPStan */
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

    private function shouldRun(Enum_ $node): bool
    {
        $hasTrait = collect($node->stmts ?? [])
            ->filter(fn ($stmt) => $stmt instanceof \PhpParser\Node\Stmt\TraitUse)
            ->flatMap(fn ($traitUse) => $traitUse->traits)
            ->filter(fn ($trait) => $this->getName($trait) === ManagesState::class)
            ->isNotEmpty();

        $hasInterface = collect($node->implements)->filter(
            fn ($interface): bool => is_a($this->getName($interface), StateMachine::class, true)
        )->isNotEmpty();

        return $hasTrait && $hasInterface;
    }

    private function shouldNotRun(Enum_ $node): bool
    {
        return ! $this->shouldRun($node);
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
