<?php

declare(strict_types=1);

namespace Tests\Support\Database\Eloquent\StateMachines\Transitions;

use PHPUnit\Framework\Attributes\Test;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Triggers\Exceptions\NotFound;
use Tests\Fixtures\Users\Status\Status;
use Tests\Fixtures\Users\Status\Triggers\NotATrigger;
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
}
