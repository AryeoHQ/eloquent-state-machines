<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\Rector\Rules;

use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;
use Tests\TestCase;
use Tooling\Rector\Rules\Provides\ValidatesInheritance;
use Tooling\Rector\Testing\ParsesNodes;
use Tooling\Rector\Testing\ResolvesRectorRules;

class AddStateMachineablePropertiesToModelDocBlocksTest extends TestCase
{
    use GetsFixtures;
    use ParsesNodes;
    use ResolvesRectorRules;
    use ValidatesInheritance;

    #[Test]
    public function it_has_rule_definition(): void
    {
        $rule = $this->resolveRule(AddStateMachineablePropertiesToModelDocBlocks::class);

        $ruleDefinition = $rule->getRuleDefinition();

        $this->assertInstanceOf(RuleDefinition::class, $ruleDefinition);
        $this->assertSame('Add StateMachineable property doc blocks to Model classes', $ruleDefinition->getDescription());
    }

    #[Test]
    public function it_does_not_refactor_non_model_classes(): void
    {
        $classNode = $this->getClassNode($this->getFixturePath('EloquentStateMachines/PlainClass.php'));

        $rule = $this->resolveRule(AddStateMachineablePropertiesToModelDocBlocks::class);
        $result = $rule->refactor($classNode);

        $this->assertNull($result);
    }

    #[Test]
    public function it_refactors_model_with_state_machineable_casts(): void
    {
        $classNode = $this->getClassNode(
            $this->getFixturePath('../Support/Users/User.php')
        );

        $this->assertInstanceOf(Class_::class, $classNode);

        $rule = $this->resolveRule(AddStateMachineablePropertiesToModelDocBlocks::class);
        $result = $rule->refactor($classNode);

        $this->assertInstanceOf(Class_::class, $result);

        $docComment = $result->getDocComment();
        $this->assertNotNull($docComment);
        $this->assertStringContainsString('@property', $docComment->getText());
        $this->assertStringContainsString('@phpstan-property', $docComment->getText());
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $classNode = $this->getClassNode(
            $this->getFixturePath('../Support/Users/User.php')
        );

        $rule = $this->resolveRule(AddStateMachineablePropertiesToModelDocBlocks::class);

        $first = $rule->refactor($classNode);
        $second = $rule->refactor($first);

        $this->assertInstanceOf(Class_::class, $second);

        $printer = new \PhpParser\PrettyPrinter\Standard;
        $this->assertSame($printer->prettyPrint([$first]), $printer->prettyPrint([$second]));
    }

    #[Test]
    public function it_does_not_duplicate_phpstan_property_after_reparse(): void
    {
        $rule = $this->resolveRule(AddStateMachineablePropertiesToModelDocBlocks::class);
        $path = $this->getFixturePath('../Support/Users/User.php');

        $first = $rule->refactor($this->getClassNode($path));

        $this->assertInstanceOf(Class_::class, $first);

        // Write the modified class back, then re-parse from disk to simulate a real second Rector pass.
        // This forces the PHPDoc parser to re-parse `StateMachine<Status>` as a GenericTypeNode.
        $original = file_get_contents($path);
        $printer = new \PhpParser\PrettyPrinter\Standard;

        try {
            file_put_contents($path, preg_replace(
                '/\/\*\*.*?\*\//s',
                $first->getDocComment()->getText(),
                $original,
                1,
            ));

            $reparsed = $this->getClassNode($path);
            $second = $rule->refactor($reparsed);

            $this->assertInstanceOf(Class_::class, $second);

            $docComment = $second->getDocComment()->getText();
            $this->assertSame(
                1,
                substr_count($docComment, '@phpstan-property'),
                'Expected exactly one @phpstan-property tag, got duplicates'
            );
        } finally {
            file_put_contents($path, $original);
        }
    }

    #[Test]
    public function it_does_not_refactor_anonymous_model_classes(): void
    {
        $code = <<<'PHP'
<?php

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Illuminate\Database\Eloquent\Model;

$model = new class extends Model {};
PHP;

        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);
        $nodes = $traverser->traverse($nodes);

        $classNode = (new NodeFinder)->findFirstInstanceOf($nodes, Class_::class);

        $this->assertInstanceOf(Class_::class, $classNode);
        $this->assertTrue($classNode->isAnonymous());

        $rule = $this->resolveRule(AddStateMachineablePropertiesToModelDocBlocks::class);
        $result = $rule->refactor($classNode);

        $this->assertNull($result);
    }

    #[Test]
    public function it_does_not_refactor_model_without_state_machineable_casts(): void
    {
        $classNode = $this->getClassNode(
            $this->getFixturePath('EloquentStateMachines/ModelWithoutStateMachineableCasts.php')
        );

        $this->assertInstanceOf(Class_::class, $classNode);

        $rule = $this->resolveRule(AddStateMachineablePropertiesToModelDocBlocks::class);
        $result = $rule->refactor($classNode);

        $this->assertNull($result);
    }
}
