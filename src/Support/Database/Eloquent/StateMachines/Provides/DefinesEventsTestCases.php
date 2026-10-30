<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Provides;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions\NotDefined;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Tooling\EloquentStateMachines\MissingEventsAttribute;

/**
 * @mixin TestCase
 */
trait DefinesEventsTestCases
{
    #[Test]
    public function it_defines_events(): void
    {
        $this->assertInstanceOf(Events::class, Status::Registered->events());
    }

    #[Test]
    public function it_throws_exception_when_events_not_defined(): void
    {
        $this->expectException(NotDefined::class);

        MissingEventsAttribute::Pending->events();
    }
}
