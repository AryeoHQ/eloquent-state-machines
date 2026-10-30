<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;

enum EventsFromConstants: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: EventNames::ACTIVATING, after: EventNames::ACTIVATED)]
    case Active = 'active';
}
