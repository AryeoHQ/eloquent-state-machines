<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Provides;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Tests\Fixtures\Support\Users\Status\Status;

/**
 * @mixin TestCase
 */
trait DefinesTransitionsTestCases
{
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
}
