<?php

declare(strict_types=1);

namespace Tests\Support\Database\Eloquent\StateMachines\Triggers;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions;
use Support\Database\Eloquent\StateMachines\Triggers\Exceptions;
use Support\Database\Eloquent\StateMachines\Triggers\Target;
use Tests\Fixtures\Users\Status\Status;
use Tests\Fixtures\Users\Status\Triggers\Activate;
use Tests\Fixtures\Users\Status\Triggers\Deactivate;
use Tests\Fixtures\Users\Status\Triggers\Exceptions\Unprocessable;
use Tests\Fixtures\Users\Status\Triggers\HandleNotDefined;
use Tests\Fixtures\Users\Status\Triggers\MultipleTargetsDefined;
use Tests\Fixtures\Users\Status\Triggers\Suspend;
use Tests\Fixtures\Users\Status\Triggers\TargetNotDefined;
use Tests\Fixtures\Users\Status\Triggers\TargetNotModel;
use Tests\Fixtures\Users\Status\Triggers\ThrowsException;
use Tests\Fixtures\Users\User;
use Tests\TestCase;

class TriggerTest extends TestCase
{
    #[Test]
    public function it_is_makeable(): void
    {
        $this->assertNotNull(Activate::make());
    }

    #[Test]
    public function it_requires_target(): void
    {
        $this->expectException(Target\Exceptions\NotDefined::class);

        $trigger = TargetNotDefined::make();

        $reflection = new \ReflectionMethod($trigger, 'target');
        $reflection->setAccessible(true);
        $reflection->invoke($trigger);
    }

    #[Test]
    public function it_can_only_define_one_target(): void
    {
        $this->expectException(Target\Exceptions\MultipleDefined::class);

        $trigger = MultipleTargetsDefined::make();

        $reflection = new \ReflectionMethod($trigger, 'target');
        $reflection->setAccessible(true);
        $reflection->invoke($trigger);
    }

    #[Test]
    public function it_can_only_target_a_model(): void
    {
        $this->expectException(Target\Exceptions\NotModel::class);

        $trigger = TargetNotModel::make();

        $reflection = new \ReflectionMethod($trigger, 'target');
        $reflection->setAccessible(true);
        $reflection->invoke($trigger);
    }

    #[Test]
    public function it_must_define_handle_method(): void
    {
        $this->expectException(Exceptions\NotProcessable::class);

        $trigger = HandleNotDefined::make()->to(Status::Activated)->on($user = User::factory()->registered()->make());

        $trigger->run();
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
    public function it_executes_handle_when_run(): void
    {
        $trigger = Activate::make()->to(Status::Activated)->on($user = User::factory()->registered()->make());

        $trigger->run();

        $this->assertNotNull($user->activated_at);
    }

    #[Test]
    public function it_resolves_handle_inputs(): void
    {
        $trigger = Deactivate::make()->to(Status::Deactivated)->on($user = User::factory()->activated()->make());

        $trigger->run();

        $this->assertNotNull($user->deactivated_at);
    }

    #[Test]
    public function it_accepts_inputs_through_constructor(): void
    {
        $trigger = Suspend::make(at: $at = now()->addDays(100))->to(Status::Suspended)->on($user = User::factory()->registered()->make());

        $trigger->run();

        $this->assertEquals($at->toDateTimeString(), $user->suspended_at);
    }

    #[Test]
    public function it_updates_model_status_when_run(): void
    {
        $trigger = Activate::make()->to(Status::Activated)->on($user = User::factory()->registered()->make());

        $trigger->run();

        $this->assertEquals(Status::Activated, $user->status->enum);
    }

    #[Test]
    public function it_fires_event_event_when_handling(): void
    {
        Event::fake([Status::Activated->events()->before, Status::Activated->events()->after]);

        $trigger = Activate::make()->to(Status::Activated)->on($user = User::factory()->registered()->make());

        $trigger->run();

        Event::assertDispatched(Status::Activated->events()->before);
        Event::assertDispatched(Status::Activated->events()->after);
    }

    #[Test]
    public function it_throws_invalid_exception_when_run_and_not_allowed(): void
    {
        $this->expectException(Transitions\Exceptions\Invalid::class);

        $trigger = Activate::make()->to(Status::Activated)->on(User::factory()->registered()->trashed()->make());

        $trigger->run();
    }

    #[Test]
    public function it_throws_original_exception_when_handle_throws_exception(): void
    {
        $this->expectException(Unprocessable::class);

        $trigger = ThrowsException::make()->to(Status::Activated)->on($user = User::factory()->registered()->make());

        $trigger->run();
    }

    #[Test]
    public function it_does_not_transition_when_handle_throws_exception(): void
    {
        $this->expectException(Unprocessable::class);

        $trigger = ThrowsException::make()->to(Status::Activated)->on($user = User::factory()->registered()->make());

        $trigger->run();

        $this->assertSame(Status::Registered, $user->status->enum);
    }

    #[Test]
    public function it_calls_failed_when_handle_throws_exception(): void
    {
        $user = User::factory()->registered()->make();

        rescue(
            fn () => ThrowsException::make()->to(Status::Activated)->on($user)->run(),
            fn () => $this->assertNotNull($user->suspended_at)
        );
    }
}
