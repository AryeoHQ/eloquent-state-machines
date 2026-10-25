<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Guard;

use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Support\Database\Eloquent\StateMachines\Triggers\Exceptions\StateChangedOutsideTrigger;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\Events\Updating;
use Tests\Fixtures\Support\Users\Status\Events\Activated;
use Tests\Fixtures\Support\Users\Status\Events\Activating;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Support\Users\Status\Triggers\Activate;
use Tests\Fixtures\Support\Users\Status\Triggers\ActivateBeforeTransition;
use Tests\Fixtures\Support\Users\Status\Triggers\ActivateWithoutTransaction;
use Tests\Fixtures\Support\Users\Status\Triggers\Onboard;
use Tests\Fixtures\Support\Users\Status\Triggers\ThrowsExceptionBeforeTransition;
use Tests\Fixtures\Support\Users\User;
use Tests\Fixtures\Support\Users\UserWithUpdatingEvent;
use Tests\TestCase;

class StateGuardTest extends TestCase
{
    #[Test]
    public function it_refuses_an_update_of_the_state_outside_a_trigger(): void
    {
        $user = User::factory()->registered()->create();

        $this->expectException(StateChangedOutsideTrigger::class);

        User::unguarded(fn () => $user->update(['status' => Status::Activated]));
    }

    #[Test]
    public function it_refuses_a_force_filled_save_of_the_state_outside_a_trigger(): void
    {
        $user = User::factory()->registered()->create();

        $this->expectException(StateChangedOutsideTrigger::class);

        $user->forceFill(['status' => Status::Activated])->save();
    }

    #[Test]
    public function it_leaves_the_stored_state_unchanged_when_it_refuses(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => $user->forceFill(['status' => Status::Activated])->save(), report: false);

        $this->assertSame(Status::Registered, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_allows_an_update_of_the_state_without_the_state_guard(): void
    {
        $user = User::factory()->registered()->create();

        StateGuard::withoutStateGuard(fn () => $user->forceFill(['status' => Status::Activated])->save());

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_restores_the_state_guard_when_the_callback_throws(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => StateGuard::withoutStateGuard(fn () => throw new RuntimeException), report: false);

        $this->expectException(StateChangedOutsideTrigger::class);

        $user->forceFill(['status' => Status::Activated])->save();
    }

    #[Test]
    public function it_allows_an_insert_with_a_state(): void
    {
        $user = (new User)->forceFill(['status' => Status::Activated]);

        $user->save();

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_allows_a_save_that_leaves_the_state_unchanged(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(Status::class, $user->getRawOriginal('status'));

        $user->forceFill(['status' => Status::Registered, 'activated_at' => now()])->save();

        $this->assertNotNull($user->refresh()->activated_at);
    }

    #[Test]
    public function it_allows_a_trigger_run_now(): void
    {
        $user = User::factory()->registered()->create();

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now();

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_allows_a_dispatched_trigger(): void
    {
        $user = User::factory()->registered()->create();

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user)->dispatch();

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_allows_a_trigger_run_by_a_queue_worker(): void
    {
        config()->set('queue.default', 'database');

        $user = User::factory()->registered()->create();

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user)->dispatch();

        app('queue.worker')->runNextJob('database', 'default', new WorkerOptions);

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_allows_a_trigger_that_transitions_before_handle(): void
    {
        $user = User::factory()->registered()->create();

        ActivateBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now();

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_allows_a_trigger_without_a_transaction(): void
    {
        $user = User::factory()->registered()->create();

        ActivateWithoutTransaction::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now();

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_allows_a_nested_trigger(): void
    {
        $user = User::factory()->registered()->create();

        Onboard::make()->to(Status::Registered)->from(Status::Registered)->on($user)->now();

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_refuses_an_update_of_the_state_after_a_trigger_finished(): void
    {
        $user = User::factory()->registered()->create();

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now();

        $this->expectException(StateChangedOutsideTrigger::class);

        $user->forceFill(['status' => Status::Deactivated])->save();
    }

    #[Test]
    public function it_refuses_an_update_of_the_state_after_a_trigger_threw(): void
    {
        $user = User::factory()->registered()->create();

        rescue(fn () => ThrowsExceptionBeforeTransition::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now(), report: false);

        $this->expectException(StateChangedOutsideTrigger::class);

        $user->forceFill(['status' => Status::Activated])->save();
    }

    #[Test]
    public function it_refuses_an_update_of_another_instance_while_a_trigger_runs(): void
    {
        $user = User::factory()->registered()->create();

        Event::listen(fn (Activating $event) => User::query()->findOrFail($event->model->getKey())->forceFill(['status' => Status::Suspended])->save());

        $this->expectException(StateChangedOutsideTrigger::class);

        Activate::make()->to(Status::Activated)->from(Status::Registered)->on($user)->now();
    }

    #[Test]
    public function it_refuses_an_increment_that_changes_the_state_outside_a_trigger(): void
    {
        $user = User::factory()->registered()->create();

        $this->expectException(StateChangedOutsideTrigger::class);

        $user->increment('id', 0, ['status' => Status::Activated]);
    }

    #[Test]
    public function it_does_not_see_a_quiet_save(): void
    {
        $user = User::factory()->registered()->create();

        $user->forceFill(['status' => Status::Activated])->saveQuietly();

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_does_not_see_a_quiet_update(): void
    {
        $user = User::factory()->registered()->create();

        User::unguarded(fn () => $user->updateQuietly(['status' => Status::Activated]));

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_does_not_see_a_save_without_events(): void
    {
        $user = User::factory()->registered()->create();

        User::withoutEvents(fn () => $user->forceFill(['status' => Status::Activated])->save());

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_does_not_see_a_query_builder_update(): void
    {
        $user = User::factory()->registered()->create();

        User::query()->whereKey($user->getKey())->update(['status' => Status::Activated->value]);

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_does_not_see_a_save_whose_dispatched_updating_event_returns_a_value(): void
    {
        Event::listen(fn (Updating $event) => true);

        $user = new UserWithUpdatingEvent;
        $user->save();

        $user->forceFill(['status' => Status::Activated])->save();

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_does_not_see_a_save_under_a_full_event_fake(): void
    {
        $user = User::factory()->registered()->create();

        Event::fake();

        $user->forceFill(['status' => Status::Activated])->save();

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
    }

    #[Test]
    public function it_refuses_an_update_of_the_state_under_a_targeted_event_fake(): void
    {
        $user = User::factory()->registered()->create();

        Event::fake([Activated::class]);

        $this->expectException(StateChangedOutsideTrigger::class);

        $user->forceFill(['status' => Status::Activated])->save();
    }

    #[Test]
    public function it_allows_a_save_scoped_to_the_instance_and_column_after_a_replay(): void
    {
        $user = User::factory()->registered()->create()->forceFill(['status' => Status::Activated]);

        Trigger::replay($user, 'status');

        StateGuard::running($user, 'status', fn () => $user->save());

        $this->assertSame(Status::Activated, $user->refresh()->status->enum);
        $this->assertNotNull($user->activated_at);
    }
}
