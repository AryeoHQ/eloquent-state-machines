<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\Rector\Rules;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\StateMachine;
use Tooling\Rector\Rules\Definitions\Attributes\Definition;
use Tooling\Rector\Rules\Rule;
use Tooling\Rector\Rules\Samples\Attributes\Sample;
use Tooling\Rules\Attributes\NodeType;

/**
 * @extends Rule<Class_>
 */
#[Definition('Add StateMachineable property doc blocks to Model classes')]
#[NodeType(Class_::class)]
#[Sample('state-machines.rector.rules.samples')]
final class AddStateMachineablePropertiesToModelDocBlocks extends Rule
{
    public PhpDocInfoFactory $phpDocInfoFactory;

    public DocBlockUpdater $docBlockUpdater;

    /** @var null|Collection<array-key, mixed> */
    private null|Collection $stateMachineableCasts = null;

    public function __construct(PhpDocInfoFactory $phpDocInfoFactory, DocBlockUpdater $docBlockUpdater)
    {
        $this->phpDocInfoFactory = $phpDocInfoFactory;
        $this->docBlockUpdater = $docBlockUpdater;
    }

    public function prepare(Node $node): void
    {
        /** @var Class_ $node */
        $class = $node->isAnonymous() ? null : $this->getName($node);

        $this->stateMachineableCasts = is_string($class) && is_a($class, Model::class, true)
            ? $this->casts($class)
            : collect();
    }

    public function shouldHandle(Node $node): bool
    {
        return $this->inherits($node, Model::class)
            && $this->stateMachineableCasts?->isNotEmpty() === true;
    }

    public function handle(Node $node): Node
    {
        $docBlock = $this->phpDocInfoFactory->createFromNodeOrEmpty($node);

        $this->stateMachineableCasts->each(function ($caster, $key) use ($docBlock): void {
            if (! $this->hasStateMachinePropertyTag($docBlock, $key)) {
                $docBlock->addTagValueNode(new PropertyTagValueNode(
                    new IntersectionTypeNode([
                        new IdentifierTypeNode('\\'.$caster),
                        new IdentifierTypeNode('\\'.StateMachine::class),
                    ]),
                    '$'.$key,
                    ''
                ));
            }

            if (! $this->hasStateMachinePhpstanPropertyTag($docBlock, $key)) {
                $docBlock->addPhpDocTagNode(new PhpDocTagNode(
                    '@phpstan-property',
                    new PropertyTagValueNode(
                        new IdentifierTypeNode('\\'.StateMachine::class.'<\\'.$caster.'>'),
                        '$'.$key,
                        ''
                    )
                ));
            }
        });

        $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);

        return $node;
    }

    private function hasStateMachinePropertyTag(PhpDocInfo $docBlock, string $key): bool
    {
        return collect($docBlock->getTagsByName('property'))->contains(
            fn (PhpDocTagNode $tag) => $tag->value instanceof PropertyTagValueNode
                && $tag->value->propertyName === '$'.$key
                && $this->isStateMachineablePropertyType($tag->value)
        );
    }

    private function hasStateMachinePhpstanPropertyTag(PhpDocInfo $docBlock, string $key): bool
    {
        return collect($docBlock->getTagsByName('phpstan-property'))->contains(
            fn (PhpDocTagNode $tag) => $tag->value instanceof PropertyTagValueNode
                && $tag->value->propertyName === '$'.$key
                && $this->isStateMachineType($tag->value->type)
        );
    }

    private function isStateMachineType(TypeNode $type): bool
    {
        if ($type instanceof IdentifierTypeNode) {
            return str_contains($type->name, StateMachine::class);
        }

        if ($type instanceof GenericTypeNode) {
            return str_contains($type->type->name, StateMachine::class);
        }

        return false;
    }

    private function isStateMachineablePropertyType(PropertyTagValueNode $value): bool
    {
        if ($value->type instanceof IdentifierTypeNode) {
            return is_a($value->type->name, StateMachineable::class, true)
                || str_contains($value->type->name, StateMachine::class);
        }

        if ($value->type instanceof IntersectionTypeNode) {
            return collect($value->type->types)->contains(
                fn ($type) => $type instanceof IdentifierTypeNode && (
                    is_a($type->name, StateMachineable::class, true)
                    || ltrim($type->name, '\\') === StateMachine::class
                )
            );
        }

        return false;
    }

    /**
     * @param  class-string<Model>  $class
     * @return Collection<array-key, mixed>
     * */
    private function casts(string $class): Collection
    {
        return collect(new $class()->getCasts() ?? [])->filter(
            fn ($caster): bool => is_a($caster, StateMachineable::class, true)
        );
    }
}
