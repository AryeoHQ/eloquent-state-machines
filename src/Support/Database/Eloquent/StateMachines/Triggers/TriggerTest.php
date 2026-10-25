<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\ManuallyFailedException;
use Illuminate\Queue\Middleware\WithoutOverlapping as WithoutOverlappingMiddleware;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Orchestra\Testbench\Attributes\RequiresDatabase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use stdClass;
use Support\Actions\Pipeline\Exceptions\Interrupted;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions;
use Tests\Fixtures\Support\Users\DualStatusUser;
use Tests\Fixtures\Support\Users\Plan\Plan;
use Tests\Fixtures\Support\Users\PlanUser;
use Tests\Fixtures\Support\Users\SecondaryUser;
use Tests\Fixtures\Support\Users\Status\Events\Activated;
use Tests\Fixtures\Support\Users\Status\Events\Activating;
use Tests\Fixtures\Support\Users\Status\Events\Registered;
use Tests\Fixtures\Support\Users\Status\Events\Registering;
use Tests\Fixtures\Support\Users\Status\Events\Suspending;
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
    public function it_resolves_the_event_from_the_container(): void
    {
        Event::listen(
            fn (Activating $event) => Context::add(
                Activating::class,
                $event->config === $this->app->make(Repository::class)
            )
        );

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on(User::factory()->registered()->create())->now();

        $this->assertTrue(Context::get(Activating::class));
    }

    #[Test]
    public function it_throws_invalid_exception_when_not_allowed_and_run_sync(): void
    {
        $this->expectException(Transitions\Exceptions\Invalid::class);

        $trigger = Activate::make()->to(Status::Activated)->from(Status::Registered)->on(User::factory()->registered()->trashed()->make());

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

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now();
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

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now();

        $this->assertSame([[1, 0]], Context::get(Activating::class));
        $this->assertSame([0], Context::get(Activated::class));
        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_checks_the_persisted_state_by_the_original_key(): void
    {
        $user = User::factory()->registered()->create();

        $user->forceFill([$user->getKeyName() => $key = $user->getKey() + 1000]);

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now();

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
            Activate::make()->to(Status::Activated)->from(Status::Registered)->on(SecondaryUser::query()->findOrFail($stale->getKey()))->now();

            DB::statement("SET lock_timeout = '200ms'");

            try {
                Activate::make()->to(Status::Activated)->from(Status::Registered)->on($stale)->now();

                $this->fail('The transition did not wait for the row lock.');
            } catch (QueryException $exception) {
                $this->assertSame('55P03', $exception->getCode());
            }

            $this->assertSame(['secondary'], Context::get(Activating::class));
        } finally {
            $holder->commit();
        }

        $this->expectException(Transitions\Exceptions\Invalid::class);

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($stale)->now();
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

    #[Test]
    public function it_survives_serialization(): void
    {
        $user = User::factory()->registered()->create();

        $trigger = WithSerializedModel::make()->to(Status::Activated)->from(Status::Registered)->on($user);

        $restored = unserialize(serialize($trigger));

        $this->assertSame(Status::Activated, $restored->to);
        $this->assertSame(Status::Registered, $restored->from);
    }

    #[Test]
    public function it_transitions_when_the_raw_original_is_an_enum(): void
    {
        $user = User::factory()->registered()->make();

        $this->assertInstanceOf(Status::class, $user->getRawOriginal('status'));

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now();

        $this->assertSame(Status::Activated, $user->status->enum);
    }

    #[Test]
    #[WithConfig('cache.default', 'array')]
    public function it_wraps_the_lifecycle_in_middleware_so_a_blocked_run_never_enters_the_lifecycle(): void
    {
        Event::fake([Activating::class]);

        $user = User::factory()->registered()->create();

        $trigger = WithMiddleware::make()->to(Status::Activated)->from(Status::Registered)->on($user);

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
    public function it_replays_a_transition_already_applied_to_the_model(): void
    {
        Event::listen(fn (Activating $event) => Context::push(Activating::class, $event->model->getConnection()->transactionLevel()));
        Event::listen(fn (Activated $event) => Context::push(Activated::class, $event->model->getConnection()->transactionLevel()));

        $user = User::factory()->registered()->create()->forceFill(['status' => Status::Activated]);

        $changes = $user->getConnection()->transaction(function () use ($user) {
            $changes = Trigger::replay($user, 'status');

            $this->assertNull(Context::get(Activated::class));

            return $changes;
        });

        $this->assertSame([1], Context::get(Activating::class));
        $this->assertSame([0], Context::get(Activated::class));
        $this->assertSame(['activated_at'], array_keys($changes));
        $this->assertSame($user->getAttributes()['activated_at'], $changes['activated_at']);
    }

    #[Test]
    public function it_writes_nothing_when_it_replays_a_transition(): void
    {
        $user = User::factory()->registered()->create()->forceFill(['status' => Status::Activated]);

        DB::listen(fn ($query) => Context::push(DB::class, $query->sql));

        Trigger::replay($user, 'status');

        $this->assertEmpty(array_filter(Context::get(DB::class, []), fn (string $sql): bool => str($sql)->lower()->startsWith('update')));
        $this->assertTrue($user->isDirty(['status', 'activated_at']));
        $this->assertSame(Status::Registered, User::query()->findOrFail($user->getKey())->status->enum);
        $this->assertNull(User::query()->findOrFail($user->getKey())->activated_at);
    }

    #[Test]
    public function it_replays_a_transition_whose_persisted_state_already_moved(): void
    {
        $user = User::factory()->registered()->create();

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update(['status' => Status::Activated->value]);

        $changes = Trigger::replay($user->forceFill(['status' => Status::Activated]), 'status');

        $this->assertArrayHasKey('activated_at', $changes);
    }

    #[Test]
    public function it_refuses_to_replay_a_blocked_transition(): void
    {
        $user = User::factory()->registered()->trashed()->create()->forceFill(['status' => Status::Activated]);

        $this->expectException(Transitions\Exceptions\Invalid::class);

        Trigger::replay($user, 'status');
    }

    #[Test]
    public function it_refuses_to_replay_a_pair_without_a_transition(): void
    {
        $user = User::factory()->activated()->create()->forceFill(['status' => Status::Registered]);

        $this->expectException(Transitions\Exceptions\Invalid::class);

        Trigger::replay($user, 'status');
    }

    #[Test]
    public function it_treats_a_replay_as_a_running_trigger(): void
    {
        Event::listen(fn (Activating $event) => $event->model->save());

        $user = User::factory()->registered()->create()->forceFill(['status' => Status::Activated]);

        Trigger::replay($user, 'status');

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_replays_the_given_column_when_two_columns_cast_to_one_enum(): void
    {
        $user = (new DualStatusUser)
            ->setRawAttributes(['status' => Status::Activated->value, 'secondary_status' => Status::Registered->value], true)
            ->forceFill(['secondary_status' => Status::Activated]);

        $changes = Trigger::replay($user, 'secondary_status');

        $this->assertSame(['activated_at'], array_keys($changes));
    }

    #[Test]
    public function it_marks_the_given_column_while_it_replays(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('secondary_status')->nullable());

        Event::listen(fn (Activating $event) => $event->model->save());

        $user = (new DualStatusUser)->forceFill(['status' => Status::Activated, 'secondary_status' => Status::Registered]);
        $user->save();

        Trigger::replay($user->forceFill(['secondary_status' => Status::Activated]), 'secondary_status');

        $stored = DualStatusUser::query()->findOrFail($user->getKey());

        $this->assertSame(Status::Activated, $stored->secondary_status->enum);
        $this->assertSame(Status::Activated, $stored->status->enum);
    }

    #[Test]
    public function it_returns_only_the_changes_the_replay_made(): void
    {
        $user = User::factory()->registered()->create()->forceFill(['name' => 'Ada']);

        $changes = Trigger::replay($user->forceFill(['status' => Status::Activated]), 'status');

        $this->assertSame(['activated_at'], array_keys($changes));
    }

    #[Test]
    public function it_refuses_to_replay_a_column_that_is_not_a_state_machine(): void
    {
        $user = User::factory()->registered()->create()->forceFill(['name' => 'Ada']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[name] on [User] is not a state machine.');

        Trigger::replay($user, 'name');
    }

    #[Test]
    public function it_refuses_to_replay_a_column_that_holds_no_state(): void
    {
        $user = User::factory()->registered()->create();
        $user->setRawAttributes([...$user->getAttributes(), 'status' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[status] on [User] holds no state to replay.');

        Trigger::replay($user, 'status');
    }

    #[Test]
    public function it_refuses_to_replay_a_raw_original_that_holds_no_state(): void
    {
        $user = (new User)->setRawAttributes([], true)->forceFill(['status' => Status::Activated]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('[status] on [User] holds no source state to replay.');

        Trigger::replay($user, 'status');
    }

    #[Test]
    public function it_refuses_to_replay_a_pair_with_more_than_one_transition(): void
    {
        $user = User::factory()->registered()->create()->forceFill(['status' => Status::Registered]);

        $this->expectException(Exceptions\Duplicate::class);
        $this->expectExceptionMessage('Multiple [Registered] triggers defined on [Registered]');

        Trigger::replay($user, 'status');
    }

    #[Test]
    public function it_names_the_source_when_it_refuses_a_replay_without_a_transition(): void
    {
        $user = User::factory()->activated()->create()->forceFill(['status' => Status::Registered]);

        $this->expectExceptionMessage('Invalid transition from [Activated] to [Registered] on [User].');

        Trigger::replay($user, 'status');
    }

    #[Test]
    public function it_names_the_source_when_it_refuses_a_blocked_replay(): void
    {
        $user = User::factory()->registered()->trashed()->create()->forceFill(['status' => Status::Activated]);

        $this->expectExceptionMessage('Invalid transition from [Registered] to [Activated] on [User].');

        Trigger::replay($user, 'status');
    }

    #[Test]
    public function it_replays_with_the_default_of_an_optional_trigger_argument(): void
    {
        $this->freezeTime();

        $user = User::factory()->registered()->create()->forceFill(['status' => Status::Suspended]);

        $changes = Trigger::replay($user, 'status');

        $this->assertSame(['suspended_at'], array_keys($changes));
        $this->assertEquals(now()->toDateTimeString(), $user->suspended_at);
    }

    #[Test]
    public function it_replays_with_the_arguments_of_the_trigger(): void
    {
        $this->freezeTime();

        $at = now()->subYear();

        $user = User::factory()->registered()->create()->forceFill(['status' => Status::Suspended]);

        $changes = Trigger::replay($user, 'status', ['at' => $at]);

        $this->assertSame(['suspended_at'], array_keys($changes));
        $this->assertEquals($at->toDateTimeString(), $user->suspended_at);
    }

    #[Test]
    public function it_matches_the_names_of_the_replay_arguments_regardless_of_case(): void
    {
        $this->freezeTime();

        $at = now()->subYear();

        $user = User::factory()->registered()->create()->forceFill(['status' => Status::Suspended]);

        Trigger::replay($user, 'status', ['AT' => $at]);

        $this->assertEquals($at->toDateTimeString(), $user->suspended_at);
    }

    #[Test]
    public function it_replays_with_a_required_argument_of_the_trigger(): void
    {
        $user = (new PlanUser)->setRawAttributes(['plan' => Plan::Free->value], true)->forceFill(['plan' => Plan::Paid]);

        $changes = Trigger::replay($user, 'plan', ['reason' => 'Needs more seats']);

        $this->assertSame(['activated_at'], array_keys($changes));
    }

    #[Test]
    public function it_refuses_to_replay_with_an_argument_the_trigger_does_not_declare(): void
    {
        Event::listen(fn (Suspending $event) => Context::add(Suspending::class, true));

        $user = User::factory()->registered()->create()->forceFill(['status' => Status::Suspended]);

        try {
            Trigger::replay($user, 'status', ['until' => now()]);

            $this->fail('The replay accepted an argument the trigger does not declare.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('[until] is not an argument of [Suspend].', $exception->getMessage());
            $this->assertNull(Context::get(Suspending::class));
            $this->assertNull($user->suspended_at);
        }
    }

    #[Test]
    public function it_refuses_to_replay_with_an_argument_for_a_trigger_without_a_constructor(): void
    {
        $user = User::factory()->registered()->create()->forceFill(['status' => Status::Activated]);

        $this->expectExceptionObject(new InvalidArgumentException('[at] is not an argument of [Activate].'));

        Trigger::replay($user, 'status', ['at' => now()]);
    }

    #[Test]
    public function it_refuses_to_replay_with_a_positional_argument(): void
    {
        $user = User::factory()->registered()->create()->forceFill(['status' => Status::Suspended]);

        $this->expectExceptionObject(new InvalidArgumentException('Replay arguments for [Suspend] must be named.'));

        Trigger::replay($user, 'status', [now()]); // @phpstan-ignore argument.type
    }

    #[Test]
    public function it_refuses_to_replay_a_transition_whose_trigger_requires_an_unresolvable_argument(): void
    {
        Event::listen(fn (Activating $event) => Context::add(Activating::class, true));

        $user = (new PlanUser)->setRawAttributes(['plan' => Plan::Free->value], true)->forceFill(['plan' => Plan::Paid]);

        try {
            Trigger::replay($user, 'plan');

            $this->fail('The replay built a trigger without its required argument.');
        } catch (BindingResolutionException) {
            $this->assertNull(Context::get(Activating::class));
            $this->assertNull($user->activated_at);
        }
    }
}
