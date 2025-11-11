<?php

declare(strict_types=1);

namespace Tests\Support\Database\Eloquent\StateMachines;

use PHPUnit\Framework\Attributes\Test;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions\NotDefined;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Tests\Fixtures\Users\Status\EventsNotDefined;
use Tests\Fixtures\Users\Status\Status;
use Tests\TestCase;

class StatusTest extends TestCase
{
    #[Test]
    public function it_defines_events(): void
    {
        $this->assertInstanceOf(Events::class, Status::Registered->events());
    }

    #[Test]
    public function it_defines_transitions(): void
    {
        $this->assertCount(2, Status::Registered->transitions());
        $this->assertCount(1, Status::Activated->transitions());
        $this->assertCount(0, Status::Deactivated->transitions());
        $this->assertCount(0, Status::Suspended->transitions());

        Status::Registered->transitions()->each(
            fn (Transition $transition) => $this->assertInstanceOf(Transition::class, $transition)
        );
    }

    #[Test]
    public function it_throws_exception_when_events_not_defined(): void
    {
        $this->expectException(NotDefined::class);

        EventsNotDefined::Pending->events();
    }
}
