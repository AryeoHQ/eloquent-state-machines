<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Provides;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions\NotDefined;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions\Reused;
use Tests\Fixtures\Support\Users\Status\Events\States\Activated;
use Tests\Fixtures\Support\Users\Status\Events\Transitions\Approving;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Tooling\EloquentStateMachines\MissingEventsAttribute;
use Tests\Fixtures\Tooling\EloquentStateMachines\ReusedAliasedStateEvent;
use Tests\Fixtures\Tooling\EloquentStateMachines\ReusedStateEvent;
use Tests\Fixtures\Tooling\EloquentStateMachines\ReusedStateEventOnTransition;
use Tests\Fixtures\Tooling\EloquentStateMachines\ReusedTransitionEvent;

/**
 * @mixin TestCase
 */
trait DefinesEventsTestCases
{
    #[Test]
    public function it_defines_events(): void
    {
        $this->assertInstanceOf(Events::class, Status::Registered->events());
    }

    #[Test]
    public function it_throws_exception_when_events_not_defined(): void
    {
        $this->expectException(NotDefined::class);

        MissingEventsAttribute::Pending->events();
    }

    #[Test]
    public function it_throws_exception_when_two_states_declare_one_event(): void
    {
        $this->expectException(Reused::class);
        $this->expectExceptionMessage('The event ['.Activated::class.'] is declared on both [Active] and [Inactive].');

        ReusedStateEvent::Active->events();
    }

    #[Test]
    public function it_throws_exception_when_two_states_declare_one_event_through_an_alias(): void
    {
        $this->expectException(Reused::class);
        $this->expectExceptionMessage('The event ['.Activated::class.'] is declared on both [Active] and [Inactive].');

        ReusedAliasedStateEvent::Active->events();
    }

    #[Test]
    public function it_throws_exception_when_two_transitions_declare_one_event(): void
    {
        $this->expectException(Reused::class);
        $this->expectExceptionMessage('The event ['.Approving::class.'] is declared on both [Pending->activate()] and [Inactive->activate()].');

        ReusedTransitionEvent::Active->events();
    }

    #[Test]
    public function it_throws_exception_when_a_state_and_a_transition_declare_one_event(): void
    {
        $this->expectException(Reused::class);
        $this->expectExceptionMessage('The event ['.Activated::class.'] is declared on both [Pending->activate()] and [Active].');

        ReusedStateEventOnTransition::Active->events();
    }
}
