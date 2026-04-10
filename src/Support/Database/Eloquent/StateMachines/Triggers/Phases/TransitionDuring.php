<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Phases;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class TransitionDuring
{
    public readonly Phase $phase;

    public function __construct(Phase $phase)
    {
        $this->phase = $phase;
    }
}
