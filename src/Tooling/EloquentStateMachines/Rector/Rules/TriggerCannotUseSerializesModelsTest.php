<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\Rector\Rules;

use Illuminate\Queue\SerializesModels;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tooling\Rector\Rules\Provides\ValidatesInheritance;
use Tooling\Rector\Testing\ParsesNodes;
use Tooling\Rector\Testing\ResolvesRectorRules;

class TriggerCannotUseSerializesModelsTest extends TestCase
{
    use ParsesNodes;
    use ResolvesRectorRules;
    use ValidatesInheritance;

    private function getSourcePath(string $filename): string
    {
        return __DIR__.'/../../../../../src/Support/Database/Eloquent/StateMachines/Triggers/'.$filename;
    }

    #[Test]
    public function does_not_modify_trigger_without_serializes_models(): void
    {
        $classNode = $this->getClassNode($this->getSourcePath('Trigger.php'));

        $this->assertTrue($this->doesNotInherit($classNode, SerializesModels::class));

        $rule = $this->resolveRule(TriggerCannotUseSerializesModels::class);
        $result = $rule->refactor($classNode);

        $this->assertNull($result);
    }
}
