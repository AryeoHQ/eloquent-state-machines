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
use Tests\Fixtures\Support\Users\SecondaryUser;
use Tests\Fixtures\Support\Users\Status\Events\Activated;
use Tests\Fixtures\Support\Users\Status\Events\Activating;
use Tests\Fixtures\Support\Users\Status\Events\Deactivating;
use Tests\Fixtures\Support\Users\Status\Events\Registered;
use Tests\Fixtures\Support\Users\Status\Events\Registering;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Support\Users\Status\Triggers\Activate;
use Tests\Fixtures\Support\Users\Status\Triggers\ActivateBeforeTransition;
use Tests\Fixtures\Support\Users\Status\Triggers\ActivateWithoutRowLock;
use Tests\Fixtures\Support\Users\Status\Triggers\CatchesInnerManualFail;
use Tests\Fixtures\Support\Users\Status\Triggers\Deactivate;
use Tests\Fixtures\Support\Users\Status\Triggers\Exceptions\Unprocessable;
use Tests\Fixtures\Support\Users\Status\Triggers\Onboard;
use Tests\Fixtures\Support\Users\Status\Triggers\Ping;
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

    #[RequiresDatabase('pgsql')]
    #[Test]
    public function it_locks_the_persisted_state_when_the_trigger_does_not_opt_out_of_the_row_lock(): void
    {
        $user = User::factory()->registered()->create();

        $queries = collect();

        DB::listen(fn (QueryExecuted $query) => $queries->push($query->sql));

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();

        $this->assertTrue($queries->contains(fn (string $sql): bool => str_ends_with($sql, 'for update')));
    }

    #[RequiresDatabase('pgsql')]
    #[Test]
    public function it_reads_the_persisted_state_without_a_row_lock_when_the_trigger_opts_out_of_the_row_lock(): void
    {
        $user = User::factory()->registered()->create();

        $queries = collect();

        DB::listen(fn (QueryExecuted $query) => $queries->push($query->sql));

        ActivateWithoutRowLock::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();

        $this->assertTrue($queries->contains(fn (string $sql): bool => str_starts_with($sql, 'select "status" from "users"')));
        $queries->each(fn (string $sql) => $this->assertStringNotContainsString('for update', $sql));
        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_refuses_a_transition_whose_persisted_state_moved_under_it_when_the_trigger_opts_out_of_the_row_lock(): void
    {
        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $this->expectException(Transitions\Exceptions\Invalid::class);

        ActivateWithoutRowLock::make()->to(Status::Activated)->from(Status::Registered)->on($user, 'status')->now();
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
    public function it_skips_before_event_for_stationary_transition(): void
    {
        Event::fake([Registering::class]);

        Ping::make()->to(Status::Registered)->from(Status::Registered)->on(User::factory()->registered()->create(), 'status')->now();

        Event::assertNotDispatched(Registering::class);
    }

    #[Test]
    public function it_skips_after_event_for_stationary_transition(): void
    {
        Event::fake([Registered::class]);

        Ping::make()->to(Status::Registered)->from(Status::Registered)->on(User::factory()->registered()->create(), 'status')->now();

        Event::assertNotDispatched(Registered::class);
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
    public function it_runs_a_trigger_queued_without_its_column_on_the_first_column_cast_to_the_enum(): void
    {
        $user = User::factory()->registered()->create();

        $serialized = serialize($user->status->activate());
        $field = serialize('field').serialize('status');

        $this->assertStringContainsString($field, $serialized);

        $payload = preg_replace_callback(
            '/^(O:\d+:"[^"]+":)(\d+)/',
            fn (array $matches): string => $matches[1].((int) $matches[2] - 1),
            str_replace($field, '', $serialized)
        );

        unserialize($payload)->now();

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
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
}
