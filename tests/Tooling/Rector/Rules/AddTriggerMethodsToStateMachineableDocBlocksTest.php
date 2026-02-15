<?php

declare(strict_types=1);

namespace Tests\Tooling\Rector\Rules;

use PhpParser\Node\Stmt\Enum_;
use PHPUnit\Framework\Attributes\Test;
use Rector\Config\RectorConfig;
use Rector\DependencyInjection\LazyContainerFactory;
use Tests\TestCase;
use Tests\Tooling\Concerns\GetsFixtures;
use Tests\Tooling\Concerns\ParsesNodes;
use Tooling\EloquentStateMachines\Rector\Rules\AddTriggerMethodsToStateMachineableDocBlocks;
use Tooling\Rector\Rules\Provides\ValidatesInheritance;

class AddTriggerMethodsToStateMachineableDocBlocksTest extends TestCase
{
    use GetsFixtures;
    use ParsesNodes;
    use ValidatesInheritance;

    private RectorConfig $rectorConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rectorConfig = (new LazyContainerFactory)->create();
        $this->rectorConfig->boot();
    }

    #[Test]
    public function it_does_not_refactor_plain_enums(): void
    {
        $enumNode = $this->getEnumNode($this->getFixturePath('Rector/PlainEnum.php'));

        $rule = $this->rectorConfig->make(AddTriggerMethodsToStateMachineableDocBlocks::class);
        $result = $rule->refactor($enumNode);

        $this->assertNull($result);
    }

    #[Test]
    public function it_refactors_state_machineable_enum(): void
    {
        $enumNode = $this->getEnumNode(
            $this->getFixturePath('../Support/Users/Status/Status.php')
        );

        $this->assertInstanceOf(Enum_::class, $enumNode);

        $rule = $this->rectorConfig->make(AddTriggerMethodsToStateMachineableDocBlocks::class);
        $result = $rule->refactor($enumNode);

        $this->assertInstanceOf(Enum_::class, $result);
    }
}
