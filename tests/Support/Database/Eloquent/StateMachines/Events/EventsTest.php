<?php

declare(strict_types=1);

namespace Tests\Support\Database\Eloquent\StateMachines\Events;

use PHPUnit\Framework\Attributes\Test;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions\NotFound;
use Tests\Fixtures\Users\Status\Events\Activated;
use Tests\Fixtures\Users\Status\Events\Activating;
use Tests\TestCase;

class EventsTest extends TestCase
{
    #[Test]
    public function it_throws_an_exception_if_the_before_event_does_not_exist(): void
    {
        $this->expectException(NotFound::class);

        new Events(before: 'NonExistentClass', after: Activated::class);
    }

    #[Test]
    public function it_throws_an_exception_if_the_after_event_does_not_exist(): void
    {
        $this->expectException(NotFound::class);

        new Events(before: Activating::class, after: 'NonExistentClass');
    }
}
