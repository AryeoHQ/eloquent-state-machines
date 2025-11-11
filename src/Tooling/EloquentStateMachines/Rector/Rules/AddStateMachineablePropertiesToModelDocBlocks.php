<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\Rector\Rules;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Rector\AbstractRector;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;

final class AddStateMachineablePropertiesToModelDocBlocks extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function __construct(public PhpDocInfoFactory $phpDocInfoFactory, public DocBlockUpdater $docBlockUpdater)
    {
        $this->phpDocInfoFactory = $phpDocInfoFactory;
        $this->docBlockUpdater = $docBlockUpdater;
    }

    public function refactor(Node $node): null|Node
    {
        $class = $this->getName($node);

        if ($this->shouldNotRun($class)) {
            return null;
        }

        $docBlock = $this->prepareDocBlock($node);

        $this->casts($class)->map(
            fn ($caster, $key): PropertyTagValueNode => new PropertyTagValueNode(
                new IdentifierTypeNode('\\'.$caster),
                '$'.$key,
                ''
            )
        )->each(
            fn (PropertyTagValueNode $property) => $docBlock->addTagValueNode($property)
        );

        $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);

        return $node;
    }

    private function shouldRun(string $class): bool
    {
        return is_subclass_of($class, Model::class, true);
    }

    private function shouldNotRun(string $class): bool
    {
        return ! $this->shouldRun($class);
    }

    private function prepareDocBlock(Node $node): PhpDocInfo
    {
        return tap(
            $this->phpDocInfoFactory->createFromNodeOrEmpty($node),
            fn (PhpDocInfo $docBlock): null => $this->removeExisting($docBlock)
        );
    }

    private function removeExisting(PhpDocInfo $docBlock): void
    {
        collect($docBlock->getTagsByName('property'))->filter(
            fn (PhpDocTagNode $tag) => $tag->value instanceof PropertyTagValueNode
                && $tag->value->type instanceof IdentifierTypeNode
                && is_a($tag->value->type->name, StateMachineable::class, true)
        )->each(
            fn (PhpDocTagNode $tag) => new PhpDocTagRemover()->removeTagValueFromNode($docBlock, $tag->value)
        );
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
