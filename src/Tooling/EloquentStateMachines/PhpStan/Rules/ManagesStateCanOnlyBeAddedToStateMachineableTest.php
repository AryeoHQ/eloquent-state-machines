<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;

/**
 * @extends RuleTestCase<ManagesStateCanOnlyBeAddedToStateMachineable>
 */
class ManagesStateCanOnlyBeAddedToStateMachineableTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new ManagesStateCanOnlyBeAddedToStateMachineable;
    }

    #[Test]
    public function it_passes_when_manages_state_is_on_state_machineable(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/ValidStateMachineable.php')], []);
    }

    #[Test]
    public function it_fails_when_manages_state_is_not_on_state_machineable(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/ManagesStateOnly.php')], [
            [
                '[ManagesState] trait can only be used on implementations of [StateMachineable].',
                11,
            ],
        ]);
    }
}
