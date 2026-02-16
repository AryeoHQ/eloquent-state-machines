<?php

declare(strict_types=1);

namespace Tests\Tooling\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Tooling\Concerns\GetsFixtures;
use Tooling\EloquentStateMachines\PhpStan\Rules\StateMachineableCanOnlyBeAddedToBackedEnum;

/**
 * @extends RuleTestCase<StateMachineableCanOnlyBeAddedToBackedEnum>
 */
class StateMachineableCanOnlyBeAddedToBackedEnumTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new StateMachineableCanOnlyBeAddedToBackedEnum(
            self::getContainer()->getByType(\PHPStan\Reflection\ReflectionProvider::class)
        );
    }

    #[Test]
    public function it_passes_when_state_machineable_is_a_backed_enum(): void
    {
        $this->analyse([$this->getFixturePath('PhpStan/StateMachines/ValidStateMachineable.php')], []);
    }

    #[Test]
    public function it_fails_when_state_machineable_is_not_a_backed_enum(): void
    {
        $this->analyse([$this->getFixturePath('PhpStan/StateMachines/NotBackedEnum.php')], [
            [
                '[StateMachineable] can only be implemented on a backed [Enum].',
                10,
            ],
        ]);
    }
}
