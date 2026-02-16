<?php

declare(strict_types=1);

namespace Tests\Tooling\Rector\Rules;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPUnit\Framework\Attributes\Test;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Tests\TestCase;
use Tests\Tooling\Concerns\GetsFixtures;
use Tooling\EloquentStateMachines\Rector\Rules\AddHandleMethodToTriggers;
use Tooling\Rector\Rules\Provides\ValidatesInheritance;
use Tooling\Rector\Testing\ParsesNodes;
use Tooling\Rector\Testing\ResolvesRectorRules;

class AddHandleMethodToTriggersTest extends TestCase
{
    use GetsFixtures;
    use ParsesNodes;
    use ResolvesRectorRules;
    use ValidatesInheritance;

    #[Test]
    public function it_has_rule_definition(): void
    {
        $rule = $this->resolveRule(AddHandleMethodToTriggers::class);

        $ruleDefinition = $rule->getRuleDefinition();

        $this->assertInstanceOf(RuleDefinition::class, $ruleDefinition);
        $this->assertSame('Add handle() method stub to Trigger classes', $ruleDefinition->getDescription());
    }

    #[Test]
    public function it_does_not_refactor_non_trigger_classes(): void
    {
        $classNode = $this->getClassNode($this->getFixturePath('Rector/PlainClass.php'));

        $rule = $this->resolveRule(AddHandleMethodToTriggers::class);
        $result = $rule->refactor($classNode);

        $this->assertNull($result);
    }

    #[Test]
    public function it_does_not_refactor_triggers_with_handle(): void
    {
        $classNode = $this->getClassNode($this->getFixturePath('PhpStan/Triggers/ValidTrigger.php'));

        $rule = $this->resolveRule(AddHandleMethodToTriggers::class);
        $result = $rule->refactor($classNode);

        $this->assertNull($result);
    }

    #[Test]
    public function it_adds_handle_method_to_trigger_without_one(): void
    {
        $classNode = $this->getClassNode($this->getFixturePath('PhpStan/Triggers/HandleNotDefined.php'));

        $this->assertInstanceOf(Class_::class, $classNode);

        $rule = $this->resolveRule(AddHandleMethodToTriggers::class);
        $result = $rule->refactor($classNode);

        $this->assertInstanceOf(Class_::class, $result);
        $this->assertTrue(
            collect($result->stmts)
                ->filter(fn ($stmt) => $stmt instanceof ClassMethod)
                ->contains(fn (ClassMethod $method) => $method->name->toString() === 'handle')
        );
    }
}
