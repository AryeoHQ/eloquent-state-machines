<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;

/**
 * @extends RuleTestCase<StateMachineableMustUseManagesState>
 */
class StateMachineableMustUseManagesStateTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new StateMachineableMustUseManagesState;
    }

    #[Test]
    public function it_passes_when_state_machineable_uses_manages_state(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/ValidStateMachineable.php')], []);
    }

    #[Test]
    public function it_fails_when_state_machineable_does_not_use_manages_state(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/MissingManagesState.php')], [
            [
                '[StateMachineable] must use the [ManagesState] trait.',
                12,
            ],
        ]);
    }
}
