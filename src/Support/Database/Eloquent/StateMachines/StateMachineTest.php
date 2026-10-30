<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines;

use PHPUnit\Framework\Attributes\Test;
use Support\Database\Eloquent\StateMachines\Attributes\Events;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\Exceptions\Duplicate;
use Support\Database\Eloquent\StateMachines\Triggers\Exceptions\Invalid;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Support\Users\User;
use Tests\Fixtures\Tooling\EloquentStateMachines\DuplicateAliasedStateEvent;
use Tests\Fixtures\Tooling\EloquentStateMachines\DuplicateEventInDeclaration;
use Tests\Fixtures\Tooling\EloquentStateMachines\DuplicateStateEvent;
use Tests\Fixtures\Tooling\EloquentStateMachines\DuplicateStateEventOnTransition;
use Tests\Fixtures\Tooling\EloquentStateMachines\DuplicateTransitionEvent;
use Tests\Fixtures\Tooling\EloquentStateMachines\DuplicateTransitionTrigger;
use Tests\Fixtures\Tooling\EloquentStateMachines\StationaryTransitionEvents;
use Tests\TestCase;

class StateMachineTest extends TestCase
{
    #[Test]
    public function it_is_makeable(): void
    {
        $this->assertInstanceOf(
            StateMachine::class,
            StateMachine::make(User::factory()->make(), Status::Registered, 'status')
        );
    }

    #[Test]
    public function it_proxies_method_calls_to_enum(): void
    {
        $stateMachine = StateMachine::make(User::factory()->make(), Status::Registered, 'status');

        collect($stateMachine->cases())->each(
            fn (Status $status) => $this->assertInstanceOf(Status::class, $status)
        );
    }

    #[Test]
    public function it_provides_access_to_the_enum(): void
    {
        $stateMachine = StateMachine::make(User::factory()->make(), Status::Registered, 'status');

        $this->assertInstanceOf(Status::class, $stateMachine->enum);
    }

    #[Test]
    public function it_resolves_trigger_from_enum(): void
    {
        $stateMachine = StateMachine::make(User::factory()->make(), Status::Registered, 'status');

        $this->assertInstanceOf(Trigger::class, $stateMachine->activate());
    }

    #[Test]
    public function it_resolves_triggers_repeatedly_from_a_valid_enum(): void
    {
        $stateMachine = StateMachine::make(User::factory()->make(), Status::Registered, 'status');

        $this->assertInstanceOf(Trigger::class, $stateMachine->activate());
        $this->assertInstanceOf(Trigger::class, $stateMachine->approve());
    }

    #[Test]
    public function it_throws_exception_for_invalid_case_transition(): void
    {
        $stateMachine = StateMachine::make(User::factory()->make(), Status::Activated, 'status');

        $this->expectException(Invalid::class);

        $stateMachine->activate();
    }

    #[Test]
    public function it_throws_exception_when_case_has_duplicate_trigger(): void
    {
        $this->expectException(Duplicate::class);

        $stateMachine = StateMachine::make(User::factory()->make(), DuplicateTransitionTrigger::Active, 'status');

        $stateMachine->activate();
    }

    #[Test]
    public function it_throws_exception_when_a_stationary_transition_declares_events(): void
    {
        $this->expectException(Events\Exceptions\Stationary::class);

        StateMachine::make(User::factory()->make(), StationaryTransitionEvents::Registered, 'status')->ping();
    }

    #[Test]
    public function it_throws_exception_when_two_states_declare_one_event(): void
    {
        $this->expectException(Events\Exceptions\Duplicate::class);

        StateMachine::make(User::factory()->make(), DuplicateStateEvent::Active, 'status')->activate();
    }

    #[Test]
    public function it_throws_exception_on_every_call_for_an_invalid_enum(): void
    {
        rescue(fn () => StateMachine::make(User::factory()->make(), DuplicateStateEvent::Active, 'status')->activate(), report: false);

        $this->expectException(Events\Exceptions\Duplicate::class);

        StateMachine::make(User::factory()->make(), DuplicateStateEvent::Active, 'status')->activate();
    }

    #[Test]
    public function it_throws_exception_when_two_states_declare_one_event_through_an_alias(): void
    {
        $this->expectException(Events\Exceptions\Duplicate::class);

        StateMachine::make(User::factory()->make(), DuplicateAliasedStateEvent::Active, 'status')->activate();
    }

    #[Test]
    public function it_throws_exception_when_two_transitions_declare_one_event(): void
    {
        $this->expectException(Events\Exceptions\Duplicate::class);

        StateMachine::make(User::factory()->make(), DuplicateTransitionEvent::Pending, 'status')->activate();
    }

    #[Test]
    public function it_throws_exception_when_a_state_and_a_transition_declare_one_event(): void
    {
        $this->expectException(Events\Exceptions\Duplicate::class);

        StateMachine::make(User::factory()->make(), DuplicateStateEventOnTransition::Pending, 'status')->activate();
    }

    #[Test]
    public function it_throws_exception_when_one_declaration_uses_an_event_for_both_before_and_after(): void
    {
        $this->expectException(Events\Exceptions\Duplicate::class);

        StateMachine::make(User::factory()->make(), DuplicateEventInDeclaration::Inactive, 'status')->activate();
    }

    #[Test]
    public function it_implements_stringable(): void
    {
        $stateMachine = StateMachine::make(User::factory()->make(), Status::Registered, 'status');

        $this->assertSame(Status::Registered->value, (string) $stateMachine);
    }

    #[Test]
    public function it_can_compare_to_an_enum_case(): void
    {
        $stateMachine = StateMachine::make(User::factory()->make(), Status::Registered, 'status');

        $this->assertTrue($stateMachine->is(Status::Registered));
        $this->assertFalse($stateMachine->is(Status::Activated));
    }

    #[Test]
    public function it_knows_when_a_state_is_terminal(): void
    {
        $model = User::factory()->make();

        $this->assertTrue(StateMachine::make($model, Status::Deactivated, 'status')->isTerminal());
        $this->assertTrue(StateMachine::make($model, Status::Suspended, 'status')->isTerminal());
        $this->assertFalse(StateMachine::make($model, Status::Registered, 'status')->isTerminal());
        $this->assertFalse(StateMachine::make($model, Status::Activated, 'status')->isTerminal());
    }
}
