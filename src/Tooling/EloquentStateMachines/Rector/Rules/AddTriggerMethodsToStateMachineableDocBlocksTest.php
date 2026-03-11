<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\Rector\Rules;

use PhpParser\Node\Stmt\Enum_;
use PHPUnit\Framework\Attributes\Test;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;
use Tests\TestCase;
use Tooling\Rector\Testing\ParsesNodesWithScope;
use Tooling\Rector\Testing\ResolvesRectorRules;

class AddTriggerMethodsToStateMachineableDocBlocksTest extends TestCase
{
    use GetsFixtures;
    use ParsesNodesWithScope;
    use ResolvesRectorRules;

    #[Test]
    public function it_has_rule_definition(): void
    {
        $rule = $this->resolveRule(AddTriggerMethodsToStateMachineableDocBlocks::class);

        $ruleDefinition = $rule->getRuleDefinition();

        $this->assertInstanceOf(RuleDefinition::class, $ruleDefinition);
        $this->assertSame('Add trigger method doc blocks to StateMachineable enums', $ruleDefinition->getDescription());
    }

    #[Test]
    public function it_does_not_refactor_plain_enums(): void
    {
        $enumNode = $this->getEnumNodeWithScope($this->getFixturePath('EloquentStateMachines/PlainEnum.php'));

        $rule = $this->resolveRule(AddTriggerMethodsToStateMachineableDocBlocks::class);
        $result = $rule->refactor($enumNode);

        $this->assertNull($result);
    }

    #[Test]
    public function it_refactors_state_machineable_enum(): void
    {
        $enumNode = $this->getEnumNodeWithScope(
            $this->getFixturePath('../Support/Users/Status/Status.php')
        );

        $this->assertInstanceOf(Enum_::class, $enumNode);

        $rule = $this->resolveRule(AddTriggerMethodsToStateMachineableDocBlocks::class);
        $result = $rule->refactor($enumNode);

        $this->assertInstanceOf(Enum_::class, $result);

        $docComment = $result->getDocComment();
        $this->assertNotNull($docComment);
        $this->assertStringContainsString('@method', $docComment->getText());
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $enumNode = $this->getEnumNodeWithScope(
            $this->getFixturePath('../Support/Users/Status/Status.php')
        );

        $rule = $this->resolveRule(AddTriggerMethodsToStateMachineableDocBlocks::class);

        $first = $rule->refactor($enumNode);
        $second = $rule->refactor($first);

        $this->assertInstanceOf(Enum_::class, $second);

        $printer = new \PhpParser\PrettyPrinter\Standard;
        $this->assertSame($printer->prettyPrint([$first]), $printer->prettyPrint([$second]));
    }
}
