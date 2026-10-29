<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Attributes\Events;

use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions\Reused;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Tooling\EloquentStateMachines\ReusedStateEvent;
use Tests\TestCase;

class DeclarationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->checked()->setValue(null, []);
    }

    #[Test]
    public function it_records_an_enum_once_events_checks_it(): void
    {
        Status::Registered->events();

        $this->assertSame([Status::class => true], $this->checked()->getValue());
    }

    #[Test]
    public function it_records_an_enum_once_transitions_checks_it(): void
    {
        Status::Registered->transitions();

        $this->assertSame([Status::class => true], $this->checked()->getValue());
    }

    #[Test]
    public function it_does_not_check_a_recorded_enum_again_on_events(): void
    {
        $this->checked()->setValue(null, [ReusedStateEvent::class => true]);

        $this->assertInstanceOf(Events::class, ReusedStateEvent::Active->events());
    }

    #[Test]
    public function it_does_not_check_a_recorded_enum_again_on_transitions(): void
    {
        $this->checked()->setValue(null, [ReusedStateEvent::class => true]);

        $this->assertEmpty(ReusedStateEvent::Active->transitions());
    }

    #[Test]
    public function it_checks_an_enum_that_reuses_an_event_on_every_call(): void
    {
        rescue(fn () => ReusedStateEvent::Active->events(), report: false);

        $this->assertSame([], $this->checked()->getValue());

        $this->expectException(Reused::class);

        ReusedStateEvent::Active->events();
    }

    private function checked(): ReflectionProperty
    {
        return new ReflectionProperty(Declarations::class, 'checked');
    }
}
