<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers;

use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use stdClass;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions;
use Tests\Fixtures\Support\Users\Status\Events\Activated;
use Tests\Fixtures\Support\Users\Status\Events\Activating;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Support\Users\Status\Triggers\Activate;
use Tests\Fixtures\Support\Users\Status\Triggers\ActivateBeforeTransition;
use Tests\Fixtures\Support\Users\Status\Triggers\Deactivate;
use Tests\Fixtures\Support\Users\Status\Triggers\Exceptions\Unprocessable;
use Tests\Fixtures\Support\Users\Status\Triggers\FailedAlsoThrows;
use Tests\Fixtures\Support\Users\Status\Triggers\Middleware\RecordExecution;
use Tests\Fixtures\Support\Users\Status\Triggers\Suspend;
use Tests\Fixtures\Support\Users\Status\Triggers\ThrowsException;
use Tests\Fixtures\Support\Users\Status\Triggers\ThrowsExceptionBeforeTransition;
use Tests\Fixtures\Support\Users\Status\Triggers\ThrowsExceptionWithoutFailed;
use Tests\Fixtures\Support\Users\Status\Triggers\WithMiddleware;
use Tests\Fixtures\Support\Users\Status\Triggers\WithSucceeded;
use Tests\Fixtures\Support\Users\Status\Triggers\WithSucceededAndFailed;
use Tests\Fixtures\Support\Users\User;
use Tests\Fixtures\Tooling\EloquentStateMachines\MissingTarget;
use Tests\Fixtures\Tooling\EloquentStateMachines\MultipleTargets;
use Tests\Fixtures\Tooling\EloquentStateMachines\TargetNotModel;
use Tests\TestCase;

class TriggerTest extends TestCase
{
    #[Test]
    public function it_does_not_use_serializes_models(): void
    {
        $this->assertNotContains(SerializesModels::class, class_uses_recursive(Trigger::class));
    }

    #[Test]
    public function it_is_makeable(): void
    {
        $this->assertNotNull(Activate::make());
    }

    #[Test]
    public function it_requires_target(): void
    {
        $this->expectException(Target\Exceptions\NotDefined::class);

        $trigger = MissingTarget::make();

        $reflection = new ReflectionMethod($trigger, 'target');
        $reflection->invoke($trigger);
    }

    #[Test]
    public function it_can_only_define_one_target(): void
    {
        $this->expectException(Target\Exceptions\MultipleDefined::class);

        $trigger = MultipleTargets::make();

        $reflection = new ReflectionMethod($trigger, 'target');
        $reflection->invoke($trigger);
    }

    #[Test]
    public function it_can_only_target_a_model(): void
    {
        $this->expectException(Target\Exceptions\NotModel::class);

        $trigger = TargetNotModel::make();

        $reflection = new ReflectionMethod($trigger, 'target');
        $reflection->invoke($trigger);
    }

    #[Test]
    public function it_determines_allowed(): void
    {
        $trigger = Deactivate::make()->to(Status::Activated)->on($user = User::factory()->registered()->make());

        $this->assertTrue($trigger->allowed());
    }

    #[Test]
    public function it_determines_blocked(): void
    {
        $trigger = Deactivate::make()->to(Status::Activated)->on($user = User::factory()->registered()->make());

        $this->assertFalse($trigger->blocked());
    }

    #[Test]
    public function it_executes_handle_when_run_sync(): void
    {
        $trigger = Activate::make()->to(Status::Activated)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertNotNull($user->activated_at);
    }

    #[Test]
    public function it_resolves_handle_inputs_when_run_sync(): void
    {
        $trigger = Deactivate::make()->to(Status::Deactivated)->on($user = User::factory()->activated()->create());

        $trigger->now();

        $this->assertNotNull($user->deactivated_at);
    }

    #[Test]
    public function it_accepts_positional_inputs_through_constructor_when_run_sync(): void
    {
        $trigger = Suspend::make($at = now()->addDays(100))->to(Status::Suspended)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertEquals($at->toDateTimeString(), $user->suspended_at);
    }

    #[Test]
    public function it_accepts_named_inputs_through_constructor_when_run_sync(): void
    {
        $trigger = Suspend::make(at: $at = now()->addDays(100))->to(Status::Suspended)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertEquals($at->toDateTimeString(), $user->suspended_at);
    }

    #[Test]
    public function it_supports_default_values_for_inputs_when_run_sync(): void
    {
        $this->freezeTime();

        $trigger = Suspend::make()->to(Status::Suspended)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertEquals(now()->toDateTimeString(), $user->suspended_at);
    }

    #[Test]
    public function it_updates_model_status_when_run_sync(): void
    {
        $trigger = Activate::make()->to(Status::Activated)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertEquals(Status::Activated, $user->status->enum);
    }

    #[Test]
    public function it_fires_before_event_before_handle_when_run_sync(): void
    {
        Event::fake([stdClass::class]);

        Event::listen(function (Activating $event) {
            $this->assertNull($event->model->activated_at);
            Event::dispatch(new stdClass);
        });

        $trigger = Activate::make()->to(Status::Activated)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertNotNull($user->activated_at);
        Event::assertDispatched(stdClass::class);
    }

    #[Test]
    public function it_fires_after_event_after_handle_when_run_sync(): void
    {
        Event::fake([stdClass::class]);

        Event::listen(function (Activated $event) {
            $this->assertNotNull($event->model->activated_at);
            Event::dispatch(new stdClass);
        });

        $trigger = Activate::make()->to(Status::Activated)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertNotNull($user->activated_at);
        Event::assertDispatched(stdClass::class);
    }

    #[Test]
    public function it_throws_invalid_exception_when_not_allowed_and_run_sync(): void
    {
        $this->expectException(Transitions\Exceptions\Invalid::class);

        $trigger = Activate::make()->to(Status::Activated)->on(User::factory()->registered()->trashed()->make());

        $trigger->now();
    }

    #[Test]
    public function it_propagates_handle_exception_when_run_sync(): void
    {
        $this->expectException(Unprocessable::class);

        $trigger = ThrowsException::make()->to(Status::Activated)->on($user = User::factory()->registered()->make());

        $trigger->now();
    }

    #[Test]
    public function it_propagates_handle_exception_without_failed_method_when_run_sync(): void
    {
        $this->expectException(Unprocessable::class);

        ThrowsExceptionWithoutFailed::make()->to(Status::Activated)->on(User::factory()->registered()->make())->now();
    }

    #[Test]
    public function it_propagates_handle_exception_when_failed_also_throws_and_run_sync(): void
    {
        $this->expectException(Unprocessable::class);

        FailedAlsoThrows::make()->to(Status::Activated)->on(User::factory()->registered()->make())->now();
    }

    #[Test]
    public function it_does_not_transition_when_handle_and_run_sync(): void
    {
        $user = User::factory()->registered()->make();

        rescue(fn () => ThrowsException::make()->to(Status::Activated)->on($user)->now());

        $this->assertSame(Status::Registered, $user->status->enum);
    }

    #[Test]
    public function it_calls_failed_when_handle_fails_and_run_sync(): void
    {
        $user = User::factory()->registered()->make();

        rescue(
            fn () => ThrowsException::make()->to(Status::Activated)->on($user)->now(),
            fn () => $this->assertNotNull($user->suspended_at)
        );
    }

    #[Test]
    public function it_rolls_back_before_transition_when_handle_throws_and_run_sync(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => ThrowsExceptionBeforeTransition::make()->to(Status::Activated)->on($user)->now());

        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_does_not_dispatch_after_event_when_handle_throws_and_run_sync(): void
    {
        Event::fake();

        $user = User::factory()->registered()->create();

        rescue(fn () => ThrowsExceptionBeforeTransition::make()->to(Status::Activated)->on($user)->now());

        Event::assertDispatched(Activating::class);
        Event::assertNotDispatched(Activated::class);
    }

    #[Test]
    public function it_persists_handle_changes_for_before_transition_when_run_sync(): void
    {
        $trigger = ActivateBeforeTransition::make()->to(Status::Activated)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertNotNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_fires_after_event_after_handle_when_run_async(): void
    {
        Event::fake([stdClass::class]);

        Event::listen(function (Activated $event) {
            $this->assertNotNull($event->model->activated_at);
            Event::dispatch(new stdClass);
        });

        $trigger = Activate::make()->to(Status::Activated)->on($user = User::factory()->registered()->create());

        $trigger->dispatch();

        $this->assertNotNull($user->refresh()->activated_at);
        Event::assertDispatched(stdClass::class);
    }

    #[Test]
    public function it_fires_before_event_before_handle_when_run_async(): void
    {
        Event::fake([stdClass::class]);

        Event::listen(function (Activating $event) {
            $this->assertNull($event->model->activated_at);
            Event::dispatch(new stdClass);
        });

        $trigger = Activate::make()->to(Status::Activated)->on($user = User::factory()->registered()->create());

        $trigger->dispatch();

        $this->assertNotNull($user->refresh()->activated_at);
        Event::assertDispatched(stdClass::class);
    }

    #[Test]
    public function it_updates_model_status_when_run_async(): void
    {
        $trigger = Activate::make()->to(Status::Activated)->on($user = User::factory()->registered()->create());

        $trigger->dispatch();

        $this->assertEquals(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_can_be_faked(): void
    {
        Activate::fake();

        Activate::make()->to(Status::Activated)->on(User::factory()->registered()->create())->dispatch();

        Activate::assertFired();
    }

    #[Test]
    public function it_transitions_before_handle_when_phase_is_before(): void
    {
        $trigger = ActivateBeforeTransition::make()->to(Status::Activated)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertEquals(Status::Activated->value, Context::get(ActivateBeforeTransition::class));
        $this->assertNotNull($user->activated_at);
    }

    #[Test]
    public function it_transitions_before_handle_when_phase_is_before_and_run_async(): void
    {
        $trigger = ActivateBeforeTransition::make()->to(Status::Activated)->on($user = User::factory()->registered()->create());

        $trigger->dispatch();

        $this->assertEquals(Status::Activated->value, Context::get(ActivateBeforeTransition::class));
        $this->assertNotNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_rolls_back_before_transition_when_handle_throws_and_run_async(): void
    {
        $user = User::factory()->registered()->create();

        rescue(function () use ($user) {
            ThrowsExceptionBeforeTransition::make()->to(Status::Activated)->on($user)->dispatch();
        }, report: false);

        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_persists_handle_changes_for_before_transition_when_run_async(): void
    {
        $trigger = ActivateBeforeTransition::make()->to(Status::Activated)->on($user = User::factory()->registered()->create());

        $trigger->dispatch();

        $this->assertNotNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_calls_succeeded_when_handle_succeeds_and_run_sync(): void
    {
        WithSucceeded::make()->to(Status::Activated)->on(User::factory()->registered()->create())->now();

        $this->assertContains(WithSucceeded::SUCCEEDED, Context::get(Trigger::class, []));
    }

    #[Test]
    public function it_calls_succeeded_when_handle_succeeds_and_run_async(): void
    {
        WithSucceeded::make()->to(Status::Activated)->on(User::factory()->registered()->create())->dispatch();

        $this->assertContains(WithSucceeded::SUCCEEDED, Context::get(Trigger::class, []));
    }

    #[Test]
    public function it_calls_failed_not_succeeded_when_handle_throws_and_run_sync(): void
    {
        rescue(
            fn () => WithSucceededAndFailed::make()->to(Status::Activated)->on(User::factory()->registered()->make())->now(),
            report: false,
        );

        $this->assertContains(WithSucceededAndFailed::FAILED, Context::get(Trigger::class, []));
        $this->assertNotContains(WithSucceededAndFailed::SUCCEEDED, Context::get(Trigger::class, []));
    }

    #[Test]
    public function it_calls_failed_not_succeeded_when_handle_throws_and_run_async(): void
    {
        try {
            WithSucceededAndFailed::make()->to(Status::Activated)->on(User::factory()->registered()->make())->dispatch();
        } catch (Unprocessable) {
            //
        }

        $this->assertContains(WithSucceededAndFailed::FAILED, Context::get(Trigger::class, []));
        $this->assertNotContains(WithSucceededAndFailed::SUCCEEDED, Context::get(Trigger::class, []));
    }

    #[Test]
    public function it_runs_user_defined_middleware_when_run_sync(): void
    {
        WithMiddleware::make()->to(Status::Activated)->on(User::factory()->registered()->create())->now();

        $this->assertContains(RecordExecution::EXECUTED, Context::get(Trigger::class, []));
    }

    #[Test]
    public function it_runs_user_defined_middleware_when_run_async(): void
    {
        WithMiddleware::make()->to(Status::Activated)->on(User::factory()->registered()->create())->dispatch();

        $this->assertContains(RecordExecution::EXECUTED, Context::get(Trigger::class, []));
    }
}
