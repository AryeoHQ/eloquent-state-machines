<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Queue\ManuallyFailedException;
use Illuminate\Queue\Middleware\WithoutOverlapping as WithoutOverlappingMiddleware;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\Attributes\RequiresDatabase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use stdClass;
use Support\Actions\Pipeline\Exceptions\Interrupted;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions;
use Tests\Fixtures\Support\Users\LegacyStatusUser;
use Tests\Fixtures\Support\Users\NonCanonicalTransitionTriggerUser;
use Tests\Fixtures\Support\Users\SecondaryUser;
use Tests\Fixtures\Support\Users\Status\Events\States\Activated;
use Tests\Fixtures\Support\Users\Status\Events\States\Activating;
use Tests\Fixtures\Support\Users\Status\Events\States\Deactivated;
use Tests\Fixtures\Support\Users\Status\Events\States\Deactivating;
use Tests\Fixtures\Support\Users\Status\Events\States\Registered;
use Tests\Fixtures\Support\Users\Status\Events\States\Registering;
use Tests\Fixtures\Support\Users\Status\Events\States\Suspended;
use Tests\Fixtures\Support\Users\Status\Events\States\Suspending;
use Tests\Fixtures\Support\Users\Status\Events\Transitions\Approved;
use Tests\Fixtures\Support\Users\Status\Events\Transitions\Approving;
use Tests\Fixtures\Support\Users\Status\Events\Transitions\Reminded;
use Tests\Fixtures\Support\Users\Status\Events\Transitions\Reminding;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Support\Users\Status\Triggers\Activate;
use Tests\Fixtures\Support\Users\Status\Triggers\ActivateBeforeTransition;
use Tests\Fixtures\Support\Users\Status\Triggers\ActivateUniquely;
use Tests\Fixtures\Support\Users\Status\Triggers\Approve;
use Tests\Fixtures\Support\Users\Status\Triggers\CatchesInnerManualFail;
use Tests\Fixtures\Support\Users\Status\Triggers\Deactivate;
use Tests\Fixtures\Support\Users\Status\Triggers\Exceptions\Unprocessable;
use Tests\Fixtures\Support\Users\Status\Triggers\Onboard;
use Tests\Fixtures\Support\Users\Status\Triggers\Ping;
use Tests\Fixtures\Support\Users\Status\Triggers\Remind;
use Tests\Fixtures\Support\Users\Status\Triggers\SavesActivation;
use Tests\Fixtures\Support\Users\Status\Triggers\SavesActivationWithoutTransaction;
use Tests\Fixtures\Support\Users\Status\Triggers\SavesTargetState;
use Tests\Fixtures\Support\Users\Status\Triggers\Suspend;
use Tests\Fixtures\Support\Users\Status\Triggers\ThrowsException;
use Tests\Fixtures\Support\Users\Status\Triggers\ThrowsExceptionAfterWrite;
use Tests\Fixtures\Support\Users\Status\Triggers\ThrowsExceptionBeforeTransition;
use Tests\Fixtures\Support\Users\Status\Triggers\WithManualFail;
use Tests\Fixtures\Support\Users\Status\Triggers\WithManualFailStationary;
use Tests\Fixtures\Support\Users\Status\Triggers\WithMiddleware;
use Tests\Fixtures\Support\Users\Status\Triggers\WithRelease;
use Tests\Fixtures\Support\Users\Status\Triggers\WithSerializedModel;
use Tests\Fixtures\Support\Users\Status\Triggers\WritesWithoutTransaction;
use Tests\Fixtures\Support\Users\User;
use Tests\Fixtures\Tooling\EloquentStateMachines\MissingTarget;
use Tests\Fixtures\Tooling\EloquentStateMachines\MultipleTargets;
use Tests\Fixtures\Tooling\EloquentStateMachines\NonCanonicalTransitionTrigger;
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
        $trigger = Deactivate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->make(), 'status');

        $this->assertTrue($trigger->allowed());
    }

    #[Test]
    public function it_determines_blocked(): void
    {
        $trigger = Deactivate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->make(), 'status');

        $this->assertFalse($trigger->blocked());
    }

    #[Test]
    public function it_executes_handle_when_run_sync(): void
    {
        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

        $trigger->now();

        $this->assertNotNull($user->activated_at);
    }

    #[Test]
    public function it_resolves_handle_inputs_when_run_sync(): void
    {
        $trigger = Deactivate::make()->to(Status::Deactivated)->from(Status::Activated)->on($user = User::factory()->activated()->create(), 'status');

        $trigger->now();

        $this->assertNotNull($user->deactivated_at);
    }

    #[Test]
    public function it_accepts_positional_inputs_through_constructor_when_run_sync(): void
    {
        $trigger = Suspend::make($at = now()->addDays(100))->to(Status::Suspended)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

        $trigger->now();

        $this->assertEquals($at->toDateTimeString(), $user->suspended_at);
    }

    #[Test]
    public function it_accepts_named_inputs_through_constructor_when_run_sync(): void
    {
        $trigger = Suspend::make(at: $at = now()->addDays(100))->to(Status::Suspended)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

        $trigger->now();

        $this->assertEquals($at->toDateTimeString(), $user->suspended_at);
    }

    #[Test]
    public function it_supports_default_values_for_inputs_when_run_sync(): void
    {
        $this->freezeTime();

        $trigger = Suspend::make()->to(Status::Suspended)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

        $trigger->now();

        $this->assertEquals(now()->toDateTimeString(), $user->suspended_at);
    }

    #[Test]
    public function it_updates_model_status_when_run_sync(): void
    {
        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

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

        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

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

        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

        $trigger->now();

        $this->assertNotNull($user->activated_at);
        Event::assertDispatched(stdClass::class);
    }

    #[Test]
    public function it_resolves_the_event_from_the_container(): void
    {
        Event::listen(
            fn (Activating $event) => Context::add(
                Activating::class,
                $event->config === $this->app->make(Repository::class)
            )
        );

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on(User::factory()->registered()->create(), 'status')->now();

        $this->assertTrue(Context::get(Activating::class));
    }

    #[Test]
    public function it_throws_invalid_exception_when_not_allowed_and_run_sync(): void
    {
        $this->expectException(Transitions\Exceptions\Invalid::class);

        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on(User::factory()->registered()->trashed()->make(), 'status');

        $trigger->now();
    }

    #[Test]
    public function it_refuses_a_transition_whose_persisted_state_moved_under_it(): void
    {
        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $this->expectException(Transitions\Exceptions\Invalid::class);

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();
    }

    #[Test]
    public function it_runs_the_lifecycle_in_a_transaction_on_the_model_connection(): void
    {
        Event::listen(fn (Activating $event) => Context::push(Activating::class, [
            $event->model->getConnection()->transactionLevel(),
            DB::connection()->transactionLevel(),
        ]));

        Event::listen(fn (Activated $event) => Context::push(Activated::class, $event->model->getConnection()->transactionLevel()));

        $user = new SecondaryUser;
        $user->save();

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();

        $this->assertSame([[1, 0]], Context::get(Activating::class));
        $this->assertSame([0], Context::get(Activated::class));
        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_checks_the_persisted_state_by_the_original_key(): void
    {
        $user = User::factory()->registered()->create();

        $user->forceFill([$user->getKeyName() => $key = $user->getKey() + 1000]);

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();

        $this->assertSame(Status::Activated, User::query()->findOrFail($key)->status->enum);
    }

    #[RequiresDatabase('pgsql')]
    #[Test]
    public function it_locks_the_persisted_state_until_the_transition_commits(): void
    {
        Event::listen(fn (Activating $event) => Context::push(Activating::class, $event->model->getConnection()->getName()));

        $stale = User::factory()->registered()->create();

        $holder = DB::connection('secondary');
        $holder->beginTransaction();

        try {
            Activate::make()->to(Status::Activated)->from(Status::Registered)->on(SecondaryUser::query()->findOrFail($stale->getKey()), 'status')->now();

            DB::statement("SET lock_timeout = '200ms'");

            try {
                Activate::make()->to(Status::Activated)->from(Status::Registered)->on($stale, 'status')->now();

                $this->fail('The transition did not wait for the row lock.');
            } catch (QueryException $exception) {
                $this->assertSame('55P03', $exception->getCode());
            }

            $this->assertSame(['secondary'], Context::get(Activating::class));
        } finally {
            $holder->commit();
        }

        $this->expectException(Transitions\Exceptions\Invalid::class);

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($stale, 'status')->now();
    }

    #[Test]
    public function it_does_not_transition_when_handle_and_run_sync(): void
    {
        $user = User::factory()->registered()->make();

        rescue(fn () => ThrowsException::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now());

        $this->assertSame(Status::Registered, $user->status->enum);
    }

    #[Test]
    public function it_calls_failed_when_handle_fails_and_run_sync(): void
    {
        $user = User::factory()->registered()->make();

        rescue(
            fn () => ThrowsException::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now(),
            fn () => $this->assertNotNull($user->suspended_at)
        );
    }

    #[Test]
    public function it_rolls_back_before_transition_when_handle_throws_and_run_sync(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => ThrowsExceptionBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now());

        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_persists_handle_changes_when_handle_throws_and_trigger_opts_out_of_transaction(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => WritesWithoutTransaction::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now());

        $this->assertNotNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_does_not_refresh_the_model_when_handle_throws_and_trigger_opts_out_of_transaction(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => WritesWithoutTransaction::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now());

        $this->assertNotNull($user->suspended_at);
    }

    #[Test]
    public function it_does_not_dispatch_after_event_when_handle_throws_and_run_sync(): void
    {
        Event::fake();

        $user = User::factory()->registered()->create();

        rescue(fn () => ThrowsExceptionBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now());

        Event::assertDispatched(Activating::class);
        Event::assertNotDispatched(Activated::class);
    }

    #[Test]
    public function it_persists_handle_changes_for_before_transition_when_run_sync(): void
    {
        $trigger = ActivateBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

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

        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

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

        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

        $trigger->dispatch();

        $this->assertNotNull($user->refresh()->activated_at);
        Event::assertDispatched(stdClass::class);
    }

    #[Test]
    public function it_updates_model_status_when_run_async(): void
    {
        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

        $trigger->dispatch();

        $this->assertEquals(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_transitions_before_handle_when_phase_is_before(): void
    {
        $trigger = ActivateBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

        $trigger->now();

        $this->assertEquals(Status::Activated->value, Context::get(ActivateBeforeTransition::class));
        $this->assertNotNull($user->activated_at);
    }

    #[Test]
    public function it_transitions_before_handle_when_phase_is_before_and_run_async(): void
    {
        $trigger = ActivateBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

        $trigger->dispatch();

        $this->assertEquals(Status::Activated->value, Context::get(ActivateBeforeTransition::class));
        $this->assertNotNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_rolls_back_before_transition_when_handle_throws_and_run_async(): void
    {
        $user = User::factory()->registered()->create();

        rescue(function () use ($user) {
            ThrowsExceptionBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->dispatch();
        }, report: false);

        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_persists_handle_changes_for_before_transition_when_run_async(): void
    {
        $trigger = ActivateBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

        $trigger->dispatch();

        $this->assertNotNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_executes_handle_for_stationary_transition(): void
    {
        $trigger = Ping::make()->to(Status::Registered)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

        $trigger->now();

        $this->assertTrue($user->refresh()->updated_at->isFuture());
    }

    #[Test]
    public function it_preserves_inner_state_changes_for_stationary_transition(): void
    {
        $trigger = Onboard::make()->to(Status::Registered)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status');

        $trigger->now();

        $this->assertNotEquals(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_fires_the_states_before_event_for_a_declared_self_transition(): void
    {
        Event::fake([Registering::class]);

        Ping::make()->to(Status::Registered)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status')->now();

        Event::assertDispatched(Registering::class, fn (Registering $event): bool => $event->model->is($user));
    }

    #[Test]
    public function it_fires_the_states_after_event_for_a_declared_self_transition(): void
    {
        Event::fake([Registered::class]);

        Ping::make()->to(Status::Registered)->from(Status::Registered)->on($user = User::factory()->registered()->create(), 'status')->now();

        Event::assertDispatched(Registered::class, fn (Registered $event): bool => $event->model->is($user));
    }

    #[Test]
    public function it_fires_the_states_events_then_its_own_for_a_declared_self_transition(): void
    {
        Event::listen(fn (Registering|Reminding|Registered|Reminded $event) => Context::push('events', $event::class));

        $user = User::factory()->registered()->create();

        $user->status->remind()->now();

        $this->assertSame([Registering::class, Reminding::class, Registered::class, Reminded::class], Context::get('events'));
        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_fires_no_events_for_an_undeclared_trigger_that_changes_no_state(): void
    {
        Event::fake([Registering::class, Registered::class, Activating::class, Activated::class]);

        $user = User::factory()->registered()->create();

        Activate::make()->to(Status::Registered)->from(Status::Registered)->on($user, 'status')->now();

        $this->assertNotNull($user->refresh()->activated_at);
        $this->assertSame(Status::Registered, $user->status->enum);
        Event::assertNotDispatched(Registering::class);
        Event::assertNotDispatched(Registered::class);
        Event::assertNotDispatched(Activating::class);
        Event::assertNotDispatched(Activated::class);
    }

    #[Test]
    public function it_fires_its_own_events_for_a_transition_declared_through_a_trigger_alias(): void
    {
        Event::listen(fn (Activating|Approving|Activated|Approved $event) => Context::push('events', $event::class));

        $user = new NonCanonicalTransitionTriggerUser;
        $user->save();

        Approve::make()->to(NonCanonicalTransitionTrigger::Activated)->from(NonCanonicalTransitionTrigger::Registered)->on($user, 'status')->now();

        $this->assertSame([Activating::class, Approving::class, Activated::class, Approved::class], Context::get('events'));
    }

    #[Test]
    public function it_fires_both_pairs_for_a_self_transition_declared_through_a_trigger_alias(): void
    {
        Event::listen(fn (Registering|Reminding|Registered|Reminded $event) => Context::push('events', $event::class));

        $user = new NonCanonicalTransitionTriggerUser;
        $user->save();

        Remind::make()->to(NonCanonicalTransitionTrigger::Registered)->from(NonCanonicalTransitionTrigger::Registered)->on($user, 'status')->now();

        $this->assertSame([Registering::class, Reminding::class, Registered::class, Reminded::class], Context::get('events'));
    }

    #[Test]
    public function it_fires_its_own_events_for_a_transition_declared_with_a_leading_backslash(): void
    {
        Event::listen(fn (Activating|Deactivating|Activated|Deactivated $event) => Context::push('events', $event::class));

        $user = new NonCanonicalTransitionTriggerUser;
        $user->save();

        Activate::make()->to(NonCanonicalTransitionTrigger::Activated)->from(NonCanonicalTransitionTrigger::Registered)->on($user, 'status')->now();

        $this->assertSame([Activating::class, Deactivating::class, Activated::class, Deactivated::class], Context::get('events'));
    }

    #[Test]
    public function it_fires_both_pairs_for_a_self_transition_declared_with_a_leading_backslash(): void
    {
        Event::listen(fn (Registering|Suspending|Registered|Suspended $event) => Context::push('events', $event::class));

        $user = new NonCanonicalTransitionTriggerUser;
        $user->save();

        Ping::make()->to(NonCanonicalTransitionTrigger::Registered)->from(NonCanonicalTransitionTrigger::Registered)->on($user, 'status')->now();

        $this->assertSame([Registering::class, Suspending::class, Registered::class, Suspended::class], Context::get('events'));
    }

    #[Test]
    public function it_rolls_back_handle_changes_when_fail_is_called_and_run_sync(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => WithManualFail::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now(), report: false);

        $this->assertNull($user->refresh()->activated_at);
        $this->assertSame(Status::Registered, $user->status->enum);
    }

    #[Test]
    public function it_refreshes_the_model_before_failed_when_fail_is_called_and_run_sync(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => WithManualFail::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now(), report: false);

        $this->assertContains(WithManualFail::FAILED, Context::get(Trigger::class, []));
        $this->assertNull(Context::get(WithManualFail::ACTIVATED_AT));
    }

    #[Test]
    public function it_routes_the_model_through_failed_when_fail_is_called_and_run_sync(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => WithManualFail::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now(), report: false);

        $this->assertNotNull($user->refresh()->suspended_at);
    }

    #[Test]
    public function it_does_not_dispatch_after_event_when_fail_is_called_and_run_sync(): void
    {
        Event::fake([Activated::class]);

        $user = User::factory()->registered()->create();

        rescue(fn () => WithManualFail::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now(), report: false);

        Event::assertNotDispatched(Activated::class);
    }

    #[Test]
    public function it_routes_the_model_through_failed_when_fail_is_called_via_sync_queue_driver(): void
    {
        $user = User::factory()->registered()->create();

        try {
            WithManualFail::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->dispatch();
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

        WithManualFail::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->dispatch();

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

        WithRelease::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();

        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_does_not_commit_transition_when_released_on_the_queue(): void
    {
        config()->set('queue.default', 'database');

        $user = User::factory()->registered()->create();

        WithRelease::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->dispatch();

        app('queue.worker')->runNextJob('database', 'default', new WorkerOptions);

        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_refreshes_the_model_before_failed_when_handle_throws_and_run_sync(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => ThrowsExceptionAfterWrite::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now(), report: false);

        $this->assertContains(ThrowsExceptionAfterWrite::FAILED, Context::get(Trigger::class, []));
        $this->assertNull(Context::get(ThrowsExceptionAfterWrite::ACTIVATED_AT));
        $this->assertNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_calls_failed_when_fail_is_called_for_stationary_transition(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => WithManualFailStationary::make()->to(Status::Registered)->from(Status::Registered)->on($user, 'status')->now(), report: false);

        $this->assertContains(WithManualFailStationary::FAILED, Context::get(Trigger::class, []));
        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_allows_outer_trigger_to_catch_inner_manual_fail(): void
    {
        $user = User::factory()->registered()->create();
        $inner = User::factory()->registered()->create();

        CatchesInnerManualFail::make($inner)->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();

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

        rescue(fn () => Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now(), report: false);

        $user->refresh();

        $this->assertTrue(Context::get(Activated::class));
        $this->assertNotNull($user->activated_at);
        $this->assertSame(Status::Activated, $user->status->enum);
    }

    #[Test]
    public function it_throws_not_saved_and_rolls_back_handle_when_a_saving_listener_vetoes_the_save_and_run_sync(): void
    {
        Event::fake([Activated::class]);

        $user = User::factory()->registered()->create();

        User::saving(fn (User $saving) => $saving->isDirty('status') ? false : null);

        $this->expectException(Exceptions\NotSaved::class);

        try {
            SavesActivation::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();
        } finally {
            Event::assertNotDispatched(Activated::class);
            $this->assertNull($user->activated_at);
            $this->assertNull($user->refresh()->activated_at);
            $this->assertSame(Status::Registered, $user->status->enum);
        }
    }

    #[Test]
    public function it_throws_not_saved_and_rolls_back_handle_when_an_updating_listener_vetoes_the_save_and_run_sync(): void
    {
        Event::fake([Activated::class]);

        $user = User::factory()->registered()->create();

        User::updating(fn (User $updating) => $updating->isDirty('status') ? false : null);

        $this->expectException(Exceptions\NotSaved::class);

        try {
            SavesActivation::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();
        } finally {
            Event::assertNotDispatched(Activated::class);
            $this->assertNull($user->refresh()->activated_at);
            $this->assertSame(Status::Registered, $user->status->enum);
        }
    }

    #[Test]
    public function it_throws_not_saved_and_rolls_back_handle_when_a_saving_listener_vetoes_the_save_and_run_async(): void
    {
        Event::fake([Activated::class]);

        $user = User::factory()->registered()->create();

        User::saving(fn (User $saving) => $saving->isDirty('status') ? false : null);

        $this->expectException(Exceptions\NotSaved::class);

        try {
            SavesActivation::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->dispatch();
        } finally {
            Event::assertNotDispatched(Activated::class);
            $this->assertNull($user->refresh()->activated_at);
            $this->assertSame(Status::Registered, $user->status->enum);
        }
    }

    #[Test]
    public function it_throws_not_saved_and_rolls_back_handle_when_a_listener_vetoes_only_the_transition_save(): void
    {
        Event::fake([Activated::class]);

        $user = User::factory()->registered()->create();

        $vetoed = false;

        User::saving(function (User $saving) use (&$vetoed) {
            if ($vetoed || ! $saving->isDirty('status')) {
                return null;
            }

            $vetoed = true;

            return false;
        });

        $this->expectException(Exceptions\NotSaved::class);

        try {
            SavesActivation::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();
        } finally {
            $this->assertTrue($vetoed);
            Event::assertNotDispatched(Activated::class);
            $this->assertNull($user->refresh()->activated_at);
            $this->assertSame(Status::Registered, $user->status->enum);
        }
    }

    #[Test]
    public function it_throws_not_saved_before_handle_when_a_listener_vetoes_only_the_transition_save_and_phase_is_before(): void
    {
        Event::fake([Activated::class]);

        $user = User::factory()->registered()->create();

        $vetoed = false;

        User::saving(function (User $saving) use (&$vetoed) {
            if ($vetoed || ! $saving->isDirty('status')) {
                return null;
            }

            $vetoed = true;

            return false;
        });

        $this->expectException(Exceptions\NotSaved::class);

        try {
            ActivateBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();
        } finally {
            $this->assertTrue($vetoed);
            $this->assertNull(Context::get(ActivateBeforeTransition::class));
            Event::assertNotDispatched(Activated::class);
            $this->assertNull($user->refresh()->activated_at);
            $this->assertSame(Status::Registered, $user->status->enum);
        }
    }

    #[Test]
    public function it_names_the_trigger_the_model_its_key_and_the_field_when_not_saved(): void
    {
        $user = User::factory()->registered()->create();

        User::saving(fn (User $saving) => $saving->isDirty('status') ? false : null);

        try {
            Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();

            $this->fail('A vetoed save did not throw.');
        } catch (Exceptions\NotSaved $exception) {
            $this->assertSame('['.Activate::class.'] did not save [User] with key ['.$user->getKey().'] on [status].', $exception->getMessage());
        }
    }

    #[Test]
    public function it_throws_not_saved_and_keeps_handle_writes_when_a_listener_vetoes_the_save_and_trigger_opts_out_of_transaction(): void
    {
        Event::fake([Activated::class]);

        $user = User::factory()->registered()->create();

        User::saving(fn (User $saving) => $saving->isDirty('status') ? false : null);

        $this->expectException(Exceptions\NotSaved::class);

        try {
            SavesActivationWithoutTransaction::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();
        } finally {
            Event::assertNotDispatched(Activated::class);
            $this->assertNotNull($user->refresh()->activated_at);
            $this->assertSame(Status::Registered, $user->status->enum);
        }
    }

    #[Test]
    public function it_throws_not_saved_and_rolls_back_the_transition_when_a_listener_vetoes_the_save_after_handle_and_phase_is_before(): void
    {
        Event::fake([Activated::class]);

        $user = User::factory()->registered()->create();

        User::saving(fn (User $saving) => $saving->isDirty('activated_at') ? false : null);

        $this->expectException(Exceptions\NotSaved::class);

        try {
            ActivateBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();
        } finally {
            Event::assertNotDispatched(Activated::class);
            $this->assertNull($user->refresh()->activated_at);
            $this->assertSame(Status::Registered, $user->status->enum);
        }
    }

    #[Test]
    public function it_throws_not_saved_and_rolls_back_handle_when_a_listener_vetoes_the_save_after_handle_and_run_without_transition(): void
    {
        Event::fake([Activated::class]);

        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        User::saving(fn (User $saving) => $saving->isDirty('activated_at') ? false : null);

        $this->expectException(Exceptions\NotSaved::class);

        try {
            $user->status->activate()->withoutTransition()->now();
        } finally {
            Event::assertNotDispatched(Activated::class);
            $this->assertNull($user->refresh()->activated_at);
        }
    }

    #[Test]
    public function it_commits_the_transition_when_a_listener_vetoes_only_the_clean_save_after_handle(): void
    {
        Event::fake([Activated::class]);

        $user = User::factory()->registered()->create();

        User::saving(fn (User $saving) => $saving->getRawOriginal('status') === Status::Activated->value ? false : null);

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();

        Event::assertDispatched(Activated::class);
        $this->assertNotNull($user->refresh()->activated_at);
        $this->assertSame(Status::Activated, $user->status->enum);
    }

    #[Test]
    public function it_throws_not_saved_and_rolls_back_the_transition_when_a_listener_dirties_the_clean_save_after_handle_and_vetoes_it(): void
    {
        Event::fake([Activated::class]);

        $user = User::factory()->registered()->create();

        User::saving(function (User $saving) {
            if ($saving->isDirty()) {
                return null;
            }

            $saving->markSuspended();

            return false;
        });

        $this->expectException(Exceptions\NotSaved::class);

        try {
            Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();
        } finally {
            Event::assertNotDispatched(Activated::class);
            $this->assertNull($user->refresh()->activated_at);
            $this->assertNull($user->suspended_at);
            $this->assertSame(Status::Registered, $user->status->enum);
        }
    }

    #[Test]
    public function it_commits_the_transition_when_handle_saves_the_target_state_and_a_listener_vetoes_clean_saves(): void
    {
        Event::fake([Activated::class]);

        $user = User::factory()->registered()->create();

        User::saving(fn (User $saving) => $saving->isDirty() ? null : false);

        SavesTargetState::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();

        Event::assertDispatched(Activated::class);
        $this->assertNotNull($user->refresh()->activated_at);
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

    #[Test]
    public function it_survives_serialization(): void
    {
        $user = User::factory()->registered()->create();

        $trigger = WithSerializedModel::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status');

        $restored = unserialize(serialize($trigger));

        $this->assertSame(Status::Activated, $restored->to);
        $this->assertSame(Status::Registered, $restored->from);
    }

    #[Test]
    public function it_transitions_when_the_raw_original_is_an_enum(): void
    {
        $user = User::factory()->registered()->make();

        $this->assertInstanceOf(Status::class, $user->getRawOriginal('status'));

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();

        $this->assertSame(Status::Activated, $user->status->enum);
    }

    #[Test]
    #[WithConfig('cache.default', 'array')]
    public function it_wraps_the_lifecycle_in_middleware_so_a_blocked_run_never_enters_the_lifecycle(): void
    {
        Event::fake([Activating::class]);

        $user = User::factory()->registered()->create();

        $trigger = WithMiddleware::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status');

        $lock = Cache::lock(
            (new WithoutOverlappingMiddleware(WithMiddleware::KEY))->getLockKey($trigger)
        );

        $this->assertTrue($lock->get());

        try {
            $this->expectException(Interrupted::class);

            $trigger->now();
        } finally {
            $lock->release();

            Event::assertNotDispatched(Activating::class);
            $this->assertNull($user->refresh()->activated_at);
            $this->assertSame(Status::Registered, $user->status->enum);
        }
    }

    #[Test]
    public function it_writes_the_column_its_proxy_was_read_from_when_run_sync(): void
    {
        $user = new LegacyStatusUser;
        $user->forceFill(['legacy_status' => Status::Registered])->save();

        $user->status->activate()->now();

        $user->refresh();

        $this->assertSame(Status::Activated, $user->status->enum);
        $this->assertSame(Status::Registered, $user->legacy_status->enum);
    }

    #[Test]
    public function it_writes_the_column_its_proxy_was_read_from_on_the_queue(): void
    {
        config()->set('queue.default', 'database');

        $user = new LegacyStatusUser;
        $user->forceFill(['legacy_status' => Status::Registered])->save();

        $user->status->activate()->dispatch();

        app('queue.worker')->runNextJob('database', 'default', new WorkerOptions);

        $user->refresh();

        $this->assertSame(Status::Activated, $user->status->enum);
        $this->assertSame(Status::Registered, $user->legacy_status->enum);
    }

    #[Test]
    public function it_writes_the_second_column_cast_to_the_enum_through_its_own_proxy(): void
    {
        $user = new LegacyStatusUser;
        $user->forceFill(['legacy_status' => Status::Registered])->save();

        $user->legacy_status->activate()->now();

        $user->refresh();

        $this->assertSame(Status::Activated, $user->legacy_status->enum);
        $this->assertSame(Status::Registered, $user->status->enum);
    }

    #[Test]
    public function it_checks_the_source_state_on_the_column_its_proxy_was_read_from(): void
    {
        $user = new LegacyStatusUser;
        $user->forceFill(['legacy_status' => Status::Activated])->save();

        $user->status->activate()->now();

        $user->refresh();

        $this->assertSame(Status::Activated, $user->status->enum);
        $this->assertSame(Status::Activated, $user->legacy_status->enum);
    }

    #[Test]
    public function it_names_the_state_of_the_column_its_proxy_was_read_from_when_invalid(): void
    {
        $user = new LegacyStatusUser;
        $user->forceFill(['legacy_status' => Status::Suspended, 'deleted_at' => now()])->save();

        $this->expectException(Transitions\Exceptions\Invalid::class);
        $this->expectExceptionMessage('Invalid transition from [Registered] to [Activated] on [LegacyStatusUser].');

        $user->status->activate()->now();
    }

    #[Test]
    public function it_writes_its_column_after_a_serialized_model_round_trip(): void
    {
        $user = new LegacyStatusUser;
        $user->forceFill(['legacy_status' => Status::Registered])->save();

        $trigger = WithSerializedModel::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status');

        unserialize(serialize($trigger))->now();

        $user->refresh();

        $this->assertSame(Status::Activated, $user->status->enum);
        $this->assertSame(Status::Registered, $user->legacy_status->enum);
    }

    #[Test]
    public function it_checks_the_persisted_state_on_the_column_its_proxy_was_read_from(): void
    {
        $user = new LegacyStatusUser;
        $user->forceFill(['legacy_status' => Status::Registered])->save();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['legacy_status' => Status::Activated->value]);

        $user->status->activate()->now();

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_names_the_state_of_the_column_its_proxy_was_read_from_when_the_persisted_state_moved(): void
    {
        $user = new LegacyStatusUser;
        $user->forceFill(['legacy_status' => Status::Suspended])->save();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $this->expectException(Transitions\Exceptions\Invalid::class);
        $this->expectExceptionMessage('Invalid transition from [Registered] to [Activated] on [LegacyStatusUser].');

        $user->status->activate()->now();
    }

    #[Test]
    #[WithConfig('cache.default', 'array')]
    public function it_refuses_a_trigger_queued_without_its_column(): void
    {
        config()->set('queue.default', 'database');

        $user = new LegacyStatusUser;
        $user->forceFill(['legacy_status' => Status::Registered, 'status' => Status::Registered])->save();

        ActivateUniquely::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->dispatch();

        $this->rewriteQueuedCommand(function (string $command): string {
            $field = serialize('field').serialize('status');

            $this->assertStringContainsString($field, $command);

            return preg_replace_callback(
                '/^(O:\d+:"[^"]+":)(\d+)/',
                fn (array $matches): string => $matches[1].((int) $matches[2] - 1),
                str_replace($field, '', $command)
            );
        });

        app('queue.worker')->runNextJob('database', 'default', new WorkerOptions);

        $exception = Context::pull(ActivateUniquely::FAILED);

        $this->assertInstanceOf(Transitions\Exceptions\NotBound::class, $exception);
        $this->assertSame('['.ActivateUniquely::class.'] is not bound to a state column on [LegacyStatusUser].', $exception->getMessage());

        $user->refresh();

        $this->assertNull($user->activated_at);
        $this->assertSame(Status::Registered, $user->status->enum);
        $this->assertSame(Status::Registered, $user->legacy_status->enum);
    }

    #[Test]
    #[WithConfig('cache.default', 'array')]
    public function it_refuses_a_unique_trigger_queued_with_a_null_column_and_releases_its_lock(): void
    {
        config()->set('queue.default', 'database');

        $user = User::factory()->registered()->create();

        $trigger = fn (): Trigger => ActivateUniquely::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status');

        $trigger()->dispatch();

        $this->rewriteQueuedCommand(function (string $command): string {
            $field = serialize('field').serialize('status');

            $this->assertStringContainsString($field, $command);

            return str_replace($field, serialize('field').serialize(null), $command);
        });

        app('queue.worker')->runNextJob('database', 'default', new WorkerOptions);

        $this->assertInstanceOf(Transitions\Exceptions\NotBound::class, Context::pull(ActivateUniquely::FAILED));
        $this->assertNull($user->refresh()->activated_at);
        $this->assertSame(Status::Registered, $user->status->enum);
        $this->assertSame(0, DB::table('jobs')->count());

        $trigger()->dispatch();

        $this->assertSame(1, DB::table('jobs')->count());
    }

    #[Test]
    public function it_refuses_a_trigger_on_an_empty_column(): void
    {
        $this->assertRefusesTheColumn('');
    }

    #[Test]
    public function it_refuses_a_trigger_on_a_mistyped_column(): void
    {
        $this->assertRefusesTheColumn('staus');
    }

    #[Test]
    public function it_refuses_a_trigger_on_a_plain_string_column_holding_the_source_state(): void
    {
        $this->assertRefusesTheColumn('previous_status');
    }

    #[Test]
    public function it_does_not_write_the_state_column_when_run_without_transition(): void
    {
        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $queries = collect();

        DB::listen(fn (QueryExecuted $query) => $queries->push($query->sql));

        $user->status->activate()->withoutTransition()->now();

        $writes = $queries->filter(fn (string $sql): bool => str_starts_with($sql, 'update "users"'));

        $this->assertNotEmpty($writes);
        $writes->each(fn (string $sql) => $this->assertStringNotContainsString('"status"', $sql));
        $this->assertNotNull($user->refresh()->activated_at);
        $this->assertSame(Status::Activated, $user->status->enum);
    }

    #[Test]
    public function it_returns_the_model_in_the_target_state_when_run_without_transition(): void
    {
        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $this->assertSame($user, $user->status->activate()->withoutTransition()->now());
        $this->assertSame(Status::Activated, $user->status->enum);
        $this->assertFalse($user->isDirty('status'));
    }

    #[Test]
    public function it_fires_both_events_when_run_without_transition(): void
    {
        Event::listen(fn (Activating $event) => Context::push(Activating::class, $event->model->status->enum));

        Event::listen(fn (Activated $event) => Context::push(Activated::class, [
            $event->model->getConnection()->transactionLevel(),
            $event->model->status->enum,
        ]));

        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $user->status->activate()->withoutTransition()->now();

        $this->assertSame([Status::Registered], Context::get(Activating::class));
        $this->assertSame([[0, Status::Activated]], Context::get(Activated::class));
    }

    #[Test]
    public function it_runs_handle_in_the_source_state_when_run_without_transition(): void
    {
        $user = User::factory()->activated()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Deactivated->value]);

        $this->app->afterResolving(Carbon::class, fn () => Context::push(Deactivate::class, $user->status->enum));

        $user->status->deactivate()->withoutTransition()->now();

        $this->assertSame([Status::Activated], Context::get(Deactivate::class));
        $this->assertNotNull($user->refresh()->deactivated_at);
    }

    #[Test]
    public function it_runs_handle_in_the_target_state_when_phase_is_before_and_run_without_transition(): void
    {
        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        ActivateBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->withoutTransition()->now();

        $this->assertSame(Status::Activated->value, Context::get(ActivateBeforeTransition::class));
        $this->assertNotNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_refuses_to_run_without_transition_when_the_persisted_state_is_not_the_target(): void
    {
        Event::fake([Deactivating::class]);

        $user = User::factory()->activated()->create();

        $this->app->afterResolving(Carbon::class, fn () => Context::push(Deactivate::class, $user->status->enum));

        $this->expectException(Transitions\Exceptions\Invalid::class);

        try {
            $user->status->deactivate()->withoutTransition()->now();
        } finally {
            $this->assertNull(Context::get(Deactivate::class));
            Event::assertNotDispatched(Deactivating::class);
        }
    }

    #[Test]
    public function it_refuses_to_run_without_transition_when_the_model_left_the_starting_state(): void
    {
        $user = User::factory()->registered()->create();

        $trigger = $user->status->activate()->withoutTransition();

        $user->status->suspend()->now();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $this->expectException(Transitions\Exceptions\Invalid::class);

        $trigger->now();
    }

    #[Test]
    public function it_refuses_to_run_without_transition_on_an_unsaved_model(): void
    {
        $this->expectException(Transitions\Exceptions\Invalid::class);

        User::factory()->registered()->make()->status->activate()->withoutTransition()->now();
    }

    #[Test]
    public function it_refuses_to_run_a_stationary_trigger_without_transition_on_an_unsaved_model(): void
    {
        $user = User::factory()->registered()->make();

        $this->expectException(Transitions\Exceptions\Invalid::class);

        try {
            $user->status->ping()->withoutTransition()->now();
        } finally {
            $this->assertFalse($user->exists);
            $this->assertDatabaseCount($user->getTable(), 0);
        }
    }

    #[Test]
    public function it_refuses_to_run_without_transition_when_not_allowed(): void
    {
        $user = User::factory()->registered()->trashed()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $queries = collect();

        DB::listen(fn (QueryExecuted $query) => $queries->push($query->sql));

        $this->expectException(Transitions\Exceptions\Invalid::class);

        try {
            $user->status->activate()->withoutTransition()->now();
        } finally {
            $writes = $queries->filter(fn (string $sql): bool => str_starts_with($sql, 'update "users"'));

            $writes->each(fn (string $sql) => $this->assertStringNotContainsString('"status"', $sql));
            $this->assertNull($user->refresh()->activated_at);
        }
    }

    #[Test]
    public function it_keeps_the_target_state_and_calls_failed_when_handle_throws_and_run_without_transition(): void
    {
        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $this->expectException(Unprocessable::class);

        try {
            ThrowsException::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->withoutTransition()->now();
        } finally {
            $this->assertSame(Status::Activated, $user->status->enum);
            $this->assertNotNull($user->suspended_at);
            $this->assertSame(Status::Activated, $user->refresh()->status->enum);
            $this->assertNotNull($user->suspended_at);
        }
    }

    #[Test]
    public function it_rethrows_a_refusal_inside_the_callers_transaction_when_run_without_transition(): void
    {
        $user = User::factory()->registered()->trashed()->create();

        rescue(fn () => DB::transaction(function () use ($user): void {
            DB::table($user->getTable())
                ->where($user->getKeyName(), $user->getKey())
                ->update(['status' => Status::Activated->value]);

            try {
                $user->status->activate()->withoutTransition()->now();
            } catch (Transitions\Exceptions\Invalid $exception) {
                Context::add(Transitions\Exceptions\Invalid::class, DB::transactionLevel());

                throw $exception;
            }
        }), report: false);

        $this->assertSame(1, Context::get(Transitions\Exceptions\Invalid::class));
        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_does_not_write_the_state_column_when_run_without_transition_on_the_queue(): void
    {
        config()->set('queue.default', 'database');

        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $queries = collect();

        DB::listen(fn (QueryExecuted $query) => $queries->push($query->sql));

        $user->status->activate()->withoutTransition()->dispatch();

        app('queue.worker')->runNextJob('database', 'default', new WorkerOptions);

        $writes = $queries->filter(fn (string $sql): bool => str_starts_with($sql, 'update "users"'));

        $this->assertNotEmpty($writes);
        $writes->each(fn (string $sql) => $this->assertStringNotContainsString('"status"', $sql));
        $this->assertNotNull($user->refresh()->activated_at);
        $this->assertSame(Status::Activated, $user->status->enum);
    }

    #[Test]
    public function it_keeps_running_without_transition_after_a_serialized_model_round_trip(): void
    {
        $user = User::factory()->registered()->create();

        $trigger = WithSerializedModel::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->withoutTransition();

        $restored = unserialize(serialize($trigger));

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $queries = collect();

        DB::listen(fn (QueryExecuted $query) => $queries->push($query->sql));

        $restored->now();

        $writes = $queries->filter(fn (string $sql): bool => str_starts_with($sql, 'update "users"'));

        $this->assertNotEmpty($writes);
        $writes->each(fn (string $sql) => $this->assertStringNotContainsString('"status"', $sql));
        $this->assertNotNull($user->refresh()->activated_at);
        $this->assertSame(Status::Activated, $user->status->enum);
    }

    #[Test]
    public function it_refuses_to_run_without_transition_when_a_serialized_model_reloads_in_the_target_state(): void
    {
        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $trigger = WithSerializedModel::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->withoutTransition();

        $restored = unserialize(serialize($trigger));

        $this->expectException(Transitions\Exceptions\Invalid::class);

        try {
            $restored->now();
        } finally {
            $this->assertNull($user->refresh()->activated_at);
        }
    }

    #[Test]
    public function it_runs_without_transition_on_the_column_its_proxy_was_read_from(): void
    {
        $user = new LegacyStatusUser;
        $user->forceFill(['legacy_status' => Status::Registered])->save();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $user->status->activate()->withoutTransition()->now();

        $this->assertSame(Status::Activated, $user->status->enum);
        $this->assertSame(Status::Registered, $user->legacy_status->enum);
        $this->assertFalse($user->isDirty(['status', 'legacy_status']));
        $this->assertSame(Status::Registered, $user->refresh()->legacy_status->enum);
    }

    #[Test]
    public function it_runs_without_transition_on_the_second_column_cast_to_the_enum_through_its_own_proxy(): void
    {
        $user = new LegacyStatusUser;
        $user->forceFill(['legacy_status' => Status::Registered])->save();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['legacy_status' => Status::Activated->value]);

        $user->legacy_status->activate()->withoutTransition()->now();

        $this->assertSame(Status::Activated, $user->legacy_status->enum);
        $this->assertSame(Status::Registered, $user->status->enum);
        $this->assertFalse($user->isDirty(['status', 'legacy_status']));
        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_fires_the_states_events_and_the_transitions_own_events(): void
    {
        Event::fake([Approving::class, Approved::class, Activating::class, Activated::class]);

        $user = User::factory()->registered()->create();

        $user->status->approve()->now();

        Event::assertDispatched(Activating::class, fn (Activating $event): bool => $event->model->is($user));
        Event::assertDispatched(Activated::class, fn (Activated $event): bool => $event->model->is($user));
        Event::assertDispatched(Approving::class, fn (Approving $event): bool => $event->model->is($user));
        Event::assertDispatched(Approved::class, fn (Approved $event): bool => $event->model->is($user));
    }

    #[Test]
    public function it_fires_only_the_states_events_for_a_transition_into_the_same_state_without_its_own(): void
    {
        Event::fake([Approving::class, Approved::class, Activating::class, Activated::class]);

        $user = User::factory()->registered()->create();

        $user->status->activate()->now();

        Event::assertDispatched(Activating::class);
        Event::assertDispatched(Activated::class);
        Event::assertNotDispatched(Approving::class);
        Event::assertNotDispatched(Approved::class);
    }

    #[Test]
    public function it_fires_both_pairs_on_the_queue(): void
    {
        config()->set('queue.default', 'database');

        Event::fake([Approving::class, Approved::class, Activating::class, Activated::class]);

        $user = User::factory()->registered()->create();

        $user->status->approve()->dispatch();

        Event::assertNotDispatched(Activating::class);
        Event::assertNotDispatched(Approving::class);

        app('queue.worker')->runNextJob('database', 'default', new WorkerOptions);

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
        Event::assertDispatched(Activating::class);
        Event::assertDispatched(Activated::class);
        Event::assertDispatched(Approving::class);
        Event::assertDispatched(Approved::class);
    }

    #[Test]
    public function it_fires_both_pairs_for_a_trigger_built_by_hand(): void
    {
        Event::fake([Approving::class, Approved::class, Activating::class, Activated::class]);

        $user = User::factory()->registered()->create();

        Approve::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();

        Event::assertDispatched(Activating::class, fn (Activating $event): bool => $event->model->is($user));
        Event::assertDispatched(Activated::class, fn (Activated $event): bool => $event->model->is($user));
        Event::assertDispatched(Approving::class, fn (Approving $event): bool => $event->model->is($user));
        Event::assertDispatched(Approved::class, fn (Approved $event): bool => $event->model->is($user));
    }

    #[Test]
    public function it_fires_the_states_event_before_the_transitions_own_in_each_phase(): void
    {
        Event::listen(fn (Activating|Approving|Activated|Approved $event) => Context::push('events', [$event::class, $event->model->getConnection()->transactionLevel()]));

        $user = User::factory()->registered()->create();

        DB::transaction(function () use ($user): void {
            $user->status->approve()->now();

            $this->assertSame([[Activating::class, 2], [Approving::class, 2]], Context::get('events'));
        });

        $this->assertSame([[Activating::class, 2], [Approving::class, 2], [Activated::class, 0], [Approved::class, 0]], Context::get('events'));
    }

    #[Test]
    public function it_fires_saved_once_when_handle_saves_and_run_without_transition(): void
    {
        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        User::saved(fn (User $saved) => Context::push(User::class, array_keys($saved->getChanges())));

        SavesActivation::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->withoutTransition()->now();

        $this->assertCount(1, $changes = Context::get(User::class, []));
        $this->assertContains('activated_at', $changes[0]);
        $this->assertNotNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_fires_saved_once_when_handle_saves_and_run_without_transition_on_the_queue(): void
    {
        config()->set('queue.default', 'database');

        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        User::saved(fn (User $saved) => Context::push(User::class, array_keys($saved->getChanges())));

        SavesActivation::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->withoutTransition()->dispatch();

        app('queue.worker')->runNextJob('database', 'default', new WorkerOptions);

        $this->assertCount(1, $changes = Context::get(User::class, []));
        $this->assertContains('activated_at', $changes[0]);
        $this->assertNotNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_saves_what_handle_left_unsaved_when_run_without_transition(): void
    {
        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        User::saved(fn (User $saved) => Context::push(User::class, array_keys($saved->getChanges())));

        $user->status->activate()->withoutTransition()->now();

        $this->assertCount(1, $changes = Context::get(User::class, []));
        $this->assertContains('activated_at', $changes[0]);
        $this->assertFalse($user->isDirty());
        $this->assertNotNull($user->refresh()->activated_at);
    }

    private function assertRefusesTheColumn(string $column): void
    {
        $user = User::factory()->registered()->create();
        $user->forceFill(['previous_status' => Status::Registered->value])->save();

        $columns = $user->refresh()->getAttributes();

        try {
            Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user, $column)->now();

            $this->fail("A trigger on [{$column}] ran.");
        } catch (Transitions\Exceptions\NotBound $exception) {
            $this->assertSame('['.Activate::class.'] is not bound to a state column on [User].', $exception->getMessage());
        }

        $this->assertSame($columns, $user->refresh()->getAttributes());
    }

    /**
     * @param  \Closure(string): string  $rewrite
     */
    private function rewriteQueuedCommand(\Closure $rewrite): void
    {
        $job = DB::table('jobs')->sole();

        $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
        $payload['data']['command'] = $rewrite($payload['data']['command']);

        DB::table('jobs')->where('id', $job->id)->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
    }
}
