<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers;

use Illuminate\Queue\ManuallyFailedException;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use stdClass;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions;
use Tests\Fixtures\Support\Users\Status\Events\Activated;
use Tests\Fixtures\Support\Users\Status\Events\Activating;
use Tests\Fixtures\Support\Users\Status\Events\Registered;
use Tests\Fixtures\Support\Users\Status\Events\Registering;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Support\Users\Status\Triggers\Activate;
use Tests\Fixtures\Support\Users\Status\Triggers\ActivateBeforeTransition;
use Tests\Fixtures\Support\Users\Status\Triggers\CatchesInnerManualFail;
use Tests\Fixtures\Support\Users\Status\Triggers\Deactivate;
use Tests\Fixtures\Support\Users\Status\Triggers\Onboard;
use Tests\Fixtures\Support\Users\Status\Triggers\Ping;
use Tests\Fixtures\Support\Users\Status\Triggers\Suspend;
use Tests\Fixtures\Support\Users\Status\Triggers\ThrowsException;
use Tests\Fixtures\Support\Users\Status\Triggers\ThrowsExceptionAfterWrite;
use Tests\Fixtures\Support\Users\Status\Triggers\ThrowsExceptionBeforeTransition;
use Tests\Fixtures\Support\Users\Status\Triggers\WithManualFail;
use Tests\Fixtures\Support\Users\Status\Triggers\WithManualFailStationary;
use Tests\Fixtures\Support\Users\Status\Triggers\WithRelease;
use Tests\Fixtures\Support\Users\Status\Triggers\WritesWithoutTransaction;
use Tests\Fixtures\Support\Users\User;
use Tests\Fixtures\Tooling\EloquentStateMachines\MissingTarget;
use Tests\Fixtures\Tooling\EloquentStateMachines\MultipleTargets;
use Tests\Fixtures\Tooling\EloquentStateMachines\TargetNotModel;
use Tests\TestCase;

class TriggerTest extends TestCase
{
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
        $trigger = Deactivate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->make());

        $this->assertTrue($trigger->allowed());
    }

    #[Test]
    public function it_determines_blocked(): void
    {
        $trigger = Deactivate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->make());

        $this->assertFalse($trigger->blocked());
    }

    #[Test]
    public function it_executes_handle_when_run_sync(): void
    {
        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertNotNull($user->activated_at);
    }

    #[Test]
    public function it_resolves_handle_inputs_when_run_sync(): void
    {
        $trigger = Deactivate::make()->to(Status::Deactivated)->from(Status::Activated)->on($user = User::factory()->activated()->create());

        $trigger->now();

        $this->assertNotNull($user->deactivated_at);
    }

    #[Test]
    public function it_accepts_positional_inputs_through_constructor_when_run_sync(): void
    {
        $trigger = Suspend::make($at = now()->addDays(100))->to(Status::Suspended)->from(Status::Registered)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertEquals($at->toDateTimeString(), $user->suspended_at);
    }

    #[Test]
    public function it_accepts_named_inputs_through_constructor_when_run_sync(): void
    {
        $trigger = Suspend::make(at: $at = now()->addDays(100))->to(Status::Suspended)->from(Status::Registered)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertEquals($at->toDateTimeString(), $user->suspended_at);
    }

    #[Test]
    public function it_supports_default_values_for_inputs_when_run_sync(): void
    {
        $this->freezeTime();

        $trigger = Suspend::make()->to(Status::Suspended)->from(Status::Registered)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertEquals(now()->toDateTimeString(), $user->suspended_at);
    }

    #[Test]
    public function it_updates_model_status_when_run_sync(): void
    {
        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create());

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

        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create());

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

        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertNotNull($user->activated_at);
        Event::assertDispatched(stdClass::class);
    }

    #[Test]
    public function it_throws_invalid_exception_when_not_allowed_and_run_sync(): void
    {
        $this->expectException(Transitions\Exceptions\Invalid::class);

        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on(User::factory()->registered()->trashed()->make());

        $trigger->now();
    }

    #[Test]
    public function it_does_not_transition_when_handle_and_run_sync(): void
    {
        $user = User::factory()->registered()->make();

        rescue(fn () => ThrowsException::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now());

        $this->assertSame(Status::Registered, $user->status->enum);
    }

    #[Test]
    public function it_calls_failed_when_handle_fails_and_run_sync(): void
    {
        $user = User::factory()->registered()->make();

        rescue(
            fn () => ThrowsException::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now(),
            fn () => $this->assertNotNull($user->suspended_at)
        );
    }

    #[Test]
    public function it_rolls_back_before_transition_when_handle_throws_and_run_sync(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => ThrowsExceptionBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now());

        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_persists_handle_changes_when_handle_throws_and_trigger_opts_out_of_transaction(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => WritesWithoutTransaction::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now());

        $this->assertNotNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_does_not_refresh_the_model_when_handle_throws_and_trigger_opts_out_of_transaction(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => WritesWithoutTransaction::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now());

        $this->assertNotNull($user->suspended_at);
    }

    #[Test]
    public function it_does_not_dispatch_after_event_when_handle_throws_and_run_sync(): void
    {
        Event::fake();

        $user = User::factory()->registered()->create();

        rescue(fn () => ThrowsExceptionBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now());

        Event::assertDispatched(Activating::class);
        Event::assertNotDispatched(Activated::class);
    }

    #[Test]
    public function it_persists_handle_changes_for_before_transition_when_run_sync(): void
    {
        $trigger = ActivateBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create());

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

        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create());

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

        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create());

        $trigger->dispatch();

        $this->assertNotNull($user->refresh()->activated_at);
        Event::assertDispatched(stdClass::class);
    }

    #[Test]
    public function it_updates_model_status_when_run_async(): void
    {
        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create());

        $trigger->dispatch();

        $this->assertEquals(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_transitions_before_handle_when_phase_is_before(): void
    {
        $trigger = ActivateBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertEquals(Status::Activated->value, Context::get(ActivateBeforeTransition::class));
        $this->assertNotNull($user->activated_at);
    }

    #[Test]
    public function it_transitions_before_handle_when_phase_is_before_and_run_async(): void
    {
        $trigger = ActivateBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create());

        $trigger->dispatch();

        $this->assertEquals(Status::Activated->value, Context::get(ActivateBeforeTransition::class));
        $this->assertNotNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_rolls_back_before_transition_when_handle_throws_and_run_async(): void
    {
        $user = User::factory()->registered()->create();

        rescue(function () use ($user) {
            ThrowsExceptionBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user)->dispatch();
        }, report: false);

        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_persists_handle_changes_for_before_transition_when_run_async(): void
    {
        $trigger = ActivateBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create());

        $trigger->dispatch();

        $this->assertNotNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_executes_handle_for_stationary_transition(): void
    {
        $trigger = Ping::make()->to(Status::Registered)->from(Status::Registered)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertTrue($user->refresh()->updated_at->isFuture());
    }

    #[Test]
    public function it_preserves_inner_state_changes_for_stationary_transition(): void
    {
        $trigger = Onboard::make()->to(Status::Registered)->from(Status::Registered)->on($user = User::factory()->registered()->create());

        $trigger->now();

        $this->assertNotEquals(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_skips_before_event_for_stationary_transition(): void
    {
        Event::fake([Registering::class]);

        Ping::make()->to(Status::Registered)->from(Status::Registered)->on(User::factory()->registered()->create())->now();

        Event::assertNotDispatched(Registering::class);
    }

    #[Test]
    public function it_skips_after_event_for_stationary_transition(): void
    {
        Event::fake([Registered::class]);

        Ping::make()->to(Status::Registered)->from(Status::Registered)->on(User::factory()->registered()->create())->now();

        Event::assertNotDispatched(Registered::class);
    }

    #[Test]
    public function it_rolls_back_handle_changes_when_fail_is_called_and_run_sync(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => WithManualFail::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now(), report: false);

        $this->assertNull($user->refresh()->activated_at);
        $this->assertSame(Status::Registered, $user->status->enum);
    }

    #[Test]
    public function it_refreshes_the_model_before_failed_when_fail_is_called_and_run_sync(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => WithManualFail::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now(), report: false);

        $this->assertContains(WithManualFail::FAILED, Context::get(Trigger::class, []));
        $this->assertNull(Context::get(WithManualFail::ACTIVATED_AT));
    }

    #[Test]
    public function it_routes_the_model_through_failed_when_fail_is_called_and_run_sync(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => WithManualFail::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now(), report: false);

        $this->assertNotNull($user->refresh()->suspended_at);
    }

    #[Test]
    public function it_does_not_dispatch_after_event_when_fail_is_called_and_run_sync(): void
    {
        Event::fake([Activated::class]);

        $user = User::factory()->registered()->create();

        rescue(fn () => WithManualFail::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now(), report: false);

        Event::assertNotDispatched(Activated::class);
    }

    #[Test]
    public function it_routes_the_model_through_failed_when_fail_is_called_via_sync_queue_driver(): void
    {
        $user = User::factory()->registered()->create();

        try {
            WithManualFail::make()->to(Status::Activated)->from(Status::Registered)->on($user)->dispatch();
        } catch (ManuallyFailedException) {
            // expected — the sync driver rethrows after Job::fail()
        }

        $this->assertNotNull($user->refresh()->suspended_at);
        $this->assertSame(Status::Registered, $user->status->enum);
    }

    #[Test]
    public function it_commits_handle_changes_and_failure_routing_atomically_when_fail_is_called_on_the_queue(): void
    {
        config()->set('queue.default', 'database');

        $user = User::factory()->registered()->create();

        WithManualFail::make()->to(Status::Activated)->from(Status::Registered)->on($user)->dispatch();

        app('queue.worker')->runNextJob('database', 'default', new WorkerOptions);

        $user->refresh();

        $this->assertNotNull($user->activated_at);
        $this->assertNotNull($user->suspended_at);
        $this->assertSame(Status::Registered, $user->status->enum);

        $context = Context::get(Trigger::class, []);

        $this->assertCount(1, array_filter($context, fn ($value) => $value === WithManualFail::FAILED));
    }

    #[Test]
    public function it_does_not_commit_transition_when_released(): void
    {
        $user = User::factory()->registered()->create();

        WithRelease::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now();

        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_does_not_commit_transition_when_released_on_the_queue(): void
    {
        config()->set('queue.default', 'database');

        $user = User::factory()->registered()->create();

        WithRelease::make()->to(Status::Activated)->from(Status::Registered)->on($user)->dispatch();

        app('queue.worker')->runNextJob('database', 'default', new WorkerOptions);

        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_refreshes_the_model_before_failed_when_handle_throws_and_run_sync(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => ThrowsExceptionAfterWrite::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now(), report: false);

        $this->assertContains(ThrowsExceptionAfterWrite::FAILED, Context::get(Trigger::class, []));
        $this->assertNull(Context::get(ThrowsExceptionAfterWrite::ACTIVATED_AT));
        $this->assertNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_calls_failed_when_fail_is_called_for_stationary_transition(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => WithManualFailStationary::make()->to(Status::Registered)->from(Status::Registered)->on($user)->now(), report: false);

        $this->assertContains(WithManualFailStationary::FAILED, Context::get(Trigger::class, []));
        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_allows_outer_trigger_to_catch_inner_manual_fail(): void
    {
        $user = User::factory()->registered()->create();
        $inner = User::factory()->registered()->create();

        CatchesInnerManualFail::make($inner)->to(Status::Activated)->from(Status::Registered)->on($user)->now();

        $this->assertContains(CatchesInnerManualFail::CAUGHT, Context::get(Trigger::class, []));
        $this->assertSame(Status::Activated, $user->refresh()->status->enum);

        $inner->refresh();

        $this->assertNull($inner->activated_at);
        $this->assertNotNull($inner->suspended_at);
        $this->assertSame(Status::Registered, $inner->status->enum);
    }

    #[Test]
    public function it_does_not_roll_back_the_transition_when_the_after_event_listener_throws(): void
    {
        Event::listen(function (Activated $event): void {
            Context::add(Activated::class, true);

            throw new RuntimeException;
        });

        $user = User::factory()->registered()->create();

        rescue(fn () => Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now(), report: false);

        $user->refresh();

        $this->assertTrue(Context::get(Activated::class));
        $this->assertNotNull($user->activated_at);
        $this->assertSame(Status::Activated, $user->status->enum);
    }

    #[Test]
    public function it_throws_invalid_exception_when_the_model_left_the_starting_state(): void
    {
        $this->expectException(Transitions\Exceptions\Invalid::class);

        $user = User::factory()->registered()->create();

        $trigger = $user->status->activate();

        $user->status->suspend()->now();

        $trigger->now();
    }

    #[Test]
    public function it_does_not_transition_when_the_model_left_the_starting_state(): void
    {
        $user = User::factory()->registered()->create();

        $trigger = $user->status->activate();

        $user->status->suspend()->now();

        rescue(fn () => $trigger->now(), report: false);

        $this->assertSame(Status::Suspended, $user->refresh()->status->enum);
        $this->assertNull($user->activated_at);
    }

    #[Test]
    public function it_transitions_when_the_model_is_still_in_the_starting_state(): void
    {
        $user = User::factory()->registered()->create();

        $user->status->activate()->now();

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }
}
