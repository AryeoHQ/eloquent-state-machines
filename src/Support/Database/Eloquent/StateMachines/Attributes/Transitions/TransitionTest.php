<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Attributes\Transitions;

use PHPUnit\Framework\Attributes\Test;
use Support\Database\Eloquent\StateMachines\Attributes\Events;
use Support\Database\Eloquent\StateMachines\Triggers\Exceptions\NotFound;
use Tests\Fixtures\Support\Users\Status\Events\Approved;
use Tests\Fixtures\Support\Users\Status\Events\Approving;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Support\Users\Status\Triggers\Activate;
use Tests\Fixtures\Support\Users\Status\Triggers\Approve;
use Tests\Fixtures\Tooling\EloquentStateMachines\NotATrigger;
use Tests\TestCase;

class TransitionTest extends TestCase
{
    #[Test]
    public function it_throws_exception_when_using_class_does_not_exist(): void
    {
        $this->expectException(NotFound::class);

        new Transition(to: Status::Registered, using: 'Missing');
    }

    #[Test]
    public function it_throws_exception_when_using_is_not_a_trigger(): void
    {
        $this->expectException(NotFound::class);

        new Transition(to: Status::Registered, using: NotATrigger::class);
    }

    #[Test]
    public function it_declares_no_events_by_default(): void
    {
        $this->assertNull(new Transition(to: Status::Activated, using: Activate::class)->events);
    }

    #[Test]
    public function it_declares_its_own_events(): void
    {
        $transition = Status::Registered->transitions()->sole(fn (Transition $transition): bool => $transition->using === Approve::class);

        $this->assertEquals(new Events\Events(before: Approving::class, after: Approved::class), $transition->events);
    }

    #[Test]
    public function it_throws_exception_when_only_the_before_event_is_declared(): void
    {
        $this->expectException(Events\Exceptions\Incomplete::class);
        $this->expectExceptionMessage('The `before` event requires a matching `after` event.');

        new Transition(to: Status::Activated, using: Approve::class, before: Approving::class);
    }

    #[Test]
    public function it_throws_exception_when_only_the_after_event_is_declared(): void
    {
        $this->expectException(Events\Exceptions\Incomplete::class);
        $this->expectExceptionMessage('The `after` event requires a matching `before` event.');

        new Transition(to: Status::Activated, using: Approve::class, after: Approved::class);
    }

    #[Test]
    public function it_throws_exception_when_the_before_event_does_not_exist(): void
    {
        $this->expectException(Events\Exceptions\NotFound::class);

        new Transition(to: Status::Activated, using: Approve::class, before: 'Missing', after: Approved::class);
    }

    #[Test]
    public function it_throws_exception_when_the_after_event_does_not_exist(): void
    {
        $this->expectException(Events\Exceptions\NotFound::class);

        new Transition(to: Status::Activated, using: Approve::class, before: Approving::class, after: 'Missing');
    }
}
