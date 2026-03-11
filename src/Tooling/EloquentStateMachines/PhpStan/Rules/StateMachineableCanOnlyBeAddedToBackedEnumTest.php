<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;

/**
 * @extends RuleTestCase<StateMachineableCanOnlyBeAddedToBackedEnum>
 */
class StateMachineableCanOnlyBeAddedToBackedEnumTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new StateMachineableCanOnlyBeAddedToBackedEnum;
    }

    #[Test]
    public function it_passes_when_state_machineable_is_a_backed_enum(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/ValidStateMachineable.php')], []);
    }

    #[Test]
    public function it_fails_when_state_machineable_is_not_a_backed_enum(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/NotBackedEnum.php')], [
            [
                '[StateMachineable] can only be implemented on a backed [Enum].',
                10,
            ],
        ]);
    }
}
