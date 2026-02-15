<?php

declare(strict_types=1);

namespace Tests\Tooling\Rector\Rules;

use PhpParser\Node\Stmt\Class_;
use PHPUnit\Framework\Attributes\Test;
use Rector\Config\RectorConfig;
use Rector\DependencyInjection\LazyContainerFactory;
use Tests\TestCase;
use Tests\Tooling\Concerns\GetsFixtures;
use Tests\Tooling\Concerns\ParsesNodes;
use Tooling\EloquentStateMachines\Rector\Rules\AddStateMachineablePropertiesToModelDocBlocks;
use Tooling\Rector\Rules\Provides\ValidatesInheritance;

class AddStateMachineablePropertiesToModelDocBlocksTest extends TestCase
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
    public function it_does_not_refactor_non_model_classes(): void
    {
        $classNode = $this->getClassNode($this->getFixturePath('Rector/PlainClass.php'));

        $rule = $this->rectorConfig->make(AddStateMachineablePropertiesToModelDocBlocks::class);
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

        $rule = $this->rectorConfig->make(AddStateMachineablePropertiesToModelDocBlocks::class);
        $result = $rule->refactor($classNode);

        $this->assertInstanceOf(Class_::class, $result);
    }
}
