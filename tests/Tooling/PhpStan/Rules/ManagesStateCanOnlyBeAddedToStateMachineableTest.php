<?php

declare(strict_types=1);

namespace Tests\Tooling\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Tooling\Concerns\GetsFixtures;
use Tooling\EloquentStateMachines\PhpStan\Rules\ManagesStateCanOnlyBeAddedToStateMachineable;

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
        $this->analyse([$this->getFixturePath('PhpStan/StateMachines/ValidStateMachineable.php')], []);
    }

    #[Test]
    public function it_fails_when_manages_state_is_not_on_state_machineable(): void
    {
        $this->analyse([$this->getFixturePath('PhpStan/StateMachines/ManagesStateOnly.php')], [
            [
                '[ManagesState] trait can only be used on implementations of [StateMachineable].',
                11,
            ],
        ]);
    }
}
