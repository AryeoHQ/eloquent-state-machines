<?php

declare(strict_types=1);

namespace Tests\Support\Database\Eloquent\StateMachines;

use PHPUnit\Framework\Attributes\Test;
use Support\Database\Eloquent\StateMachines\StateMachine;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\Exceptions\Duplicate;
use Support\Database\Eloquent\StateMachines\Triggers\Exceptions\Invalid;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Support\Users\User;
use Tests\Fixtures\Tooling\PhpStan\StateMachines\DuplicateTransitionTrigger;
use Tests\TestCase;

class StateMachineTest extends TestCase
{
    #[Test]
    public function it_is_makeable(): void
    {
        $this->assertInstanceOf(
            StateMachine::class,
            StateMachine::make(User::make(), Status::Registered)
        );
    }

    #[Test]
    public function it_proxies_method_calls_to_enum(): void
    {
        $stateMachine = StateMachine::make(User::make(), Status::Registered);

        collect($stateMachine->cases())->each(
            fn (Status $status) => $this->assertInstanceOf(Status::class, $status)
        );
    }

    #[Test]
    public function it_provides_access_to_the_enum(): void
    {
        $stateMachine = StateMachine::make(User::make(), Status::Registered);

        $this->assertInstanceOf(Status::class, $stateMachine->enum);
    }

    #[Test]
    public function it_resolves_trigger_from_enum(): void
    {
        $stateMachine = StateMachine::make(User::make(), Status::Registered);

        $this->assertInstanceOf(Trigger::class, $stateMachine->activate());
    }

    #[Test]
    public function it_throws_exception_for_invalid_case_transition(): void
    {
        $stateMachine = StateMachine::make(User::make(), Status::Activated);

        $this->expectException(Invalid::class);

        $stateMachine->activate();
    }

    #[Test]
    public function it_throws_exception_when_case_has_duplicate_trigger(): void
    {
        $this->expectException(Duplicate::class);

        $stateMachine = StateMachine::make(User::make(), DuplicateTransitionTrigger::Active);

        $stateMachine->activate();
    }

    #[Test]
    public function it_implements_stringable(): void
    {
        $stateMachine = StateMachine::make(User::make(), Status::Registered);

        $this->assertSame(Status::Registered->value, (string) $stateMachine);
    }
}
