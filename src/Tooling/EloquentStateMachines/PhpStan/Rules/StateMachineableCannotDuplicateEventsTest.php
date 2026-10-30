<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;

/**
 * @extends RuleTestCase<StateMachineableCannotDuplicateEvents>
 */
class StateMachineableCannotDuplicateEventsTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new StateMachineableCannotDuplicateEvents;
    }

    #[Test]
    public function it_passes_when_no_event_is_declared_twice(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/ValidStateMachineable.php')], []);
    }

    #[Test]
    public function it_passes_when_events_come_from_constants(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/EventsFromConstants.php')], []);
    }

    #[Test]
    public function it_fails_when_two_states_declare_one_event(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/DuplicateStateEvent.php')], [
            [
                '[Tests\Fixtures\Support\Users\Status\Events\States\Activated] duplicated: An event can only be declared once per state machine.',
                25,
            ],
        ]);
    }

    #[Test]
    public function it_fails_when_a_state_and_a_transition_declare_one_event(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/DuplicateStateEventOnTransition.php')], [
            [
                '[Tests\Fixtures\Support\Users\Status\Events\States\Activated] duplicated: An event can only be declared once per state machine.',
                30,
            ],
        ]);
    }

    #[Test]
    public function it_fails_when_two_transitions_on_different_cases_declare_one_event(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/DuplicateTransitionEvent.php')], [
            [
                '[Tests\Fixtures\Support\Users\Status\Events\Transitions\Approving] duplicated: An event can only be declared once per state machine.',
                37,
            ],
            [
                '[Tests\Fixtures\Support\Users\Status\Events\Transitions\Approved] duplicated: An event can only be declared once per state machine.',
                37,
            ],
        ]);
    }

    #[Test]
    public function it_fails_when_one_declaration_uses_an_event_for_both_before_and_after(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/DuplicateEventInDeclaration.php')], [
            [
                '[Tests\Fixtures\Support\Users\Status\Events\States\Activated] duplicated: An event can only be declared once per state machine.',
                28,
            ],
        ]);
    }
}
