<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Provides;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Support\Users\Status\Status;

/**
 * @mixin TestCase
 */
trait DefinesTransitionsTestCases
{
    #[Test]
    public function it_defines_transitions(): void
    {
        Status::Registered->assertDefinesTransitions(Status::Activated, Status::Suspended);
        Status::Activated->assertDefinesTransitions(Status::Deactivated);
        Status::Deactivated->assertIsTerminal();
        Status::Suspended->assertIsTerminal();
    }

    #[Test]
    public function it_is_order_independent(): void
    {
        Status::Registered->assertDefinesTransitions(Status::Suspended, Status::Activated);
    }

    #[Test]
    public function it_fails_when_extra_transition_defined(): void
    {
        $this->expectException(ExpectationFailedException::class);

        Status::Registered->assertDefinesTransitions(Status::Activated);
    }

    #[Test]
    public function it_fails_when_missing_transition_expected(): void
    {
        $this->expectException(ExpectationFailedException::class);

        Status::Registered->assertDefinesTransitions(Status::Activated, Status::Suspended, Status::Deactivated);
    }

    #[Test]
    public function it_fails_when_asserting_terminal_on_non_terminal(): void
    {
        $this->expectException(ExpectationFailedException::class);

        Status::Registered->assertIsTerminal();
    }
}
