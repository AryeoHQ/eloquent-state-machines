<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\Rector\Rules;

use PhpParser\Node\Stmt\Class_;
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
}
