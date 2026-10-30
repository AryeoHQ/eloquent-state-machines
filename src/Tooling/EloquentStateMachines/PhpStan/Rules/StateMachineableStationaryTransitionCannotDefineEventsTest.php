<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;

/**
 * @extends RuleTestCase<StateMachineableStationaryTransitionCannotDefineEvents>
 */
class StateMachineableStationaryTransitionCannotDefineEventsTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new StateMachineableStationaryTransitionCannotDefineEvents;
    }

    #[Test]
    public function it_passes_when_no_stationary_transition_defines_events(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/ValidStateMachineable.php')], []);
    }

    #[Test]
    public function it_fails_when_a_stationary_transition_defines_events(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/StationaryTransitionEvents.php')], [
            [
                '[Tests\Fixtures\Support\Users\Status\Triggers\Ping] stationary: A transition to its own case cannot define events.',
                34,
            ],
            [
                '[Tests\Fixtures\Support\Users\Status\Triggers\Activate] stationary: A transition to its own case cannot define events.',
                38,
            ],
            [
                '[Tests\Fixtures\Support\Users\Status\Triggers\Deactivate] stationary: A transition to its own case cannot define events.',
                41,
            ],
        ]);
    }
}
