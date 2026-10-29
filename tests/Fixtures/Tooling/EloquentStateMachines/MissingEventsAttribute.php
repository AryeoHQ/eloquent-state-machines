<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tests\Fixtures\Support\Users\Status\Events\States\Activated;
use Tests\Fixtures\Support\Users\Status\Events\States\Activating;

enum MissingEventsAttribute: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: Activating::class, after: Activated::class)]
    case Active = 'active';

    case Pending = 'pending';
}
