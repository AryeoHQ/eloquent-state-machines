<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tests\Fixtures\Support\Users\Status\Events\States\Activated;
use Tests\Fixtures\Support\Users\Status\Events\States\Activating;
use Tests\Fixtures\Support\Users\Status\Events\States\Registered;
use Tests\Fixtures\Support\Users\Status\Events\States\Registering;
use Tests\Fixtures\Support\Users\Status\Events\Transitions\Approving;
use Tests\Fixtures\Support\Users\Status\Triggers\Activate;

enum ReusedStateEventOnTransition: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: Registering::class, after: Registered::class)]
    #[Transition(to: self::Active, using: Activate::class, before: Approving::class, after: Activated::class)]
    case Pending = 'pending';

    #[Events(before: Activating::class, after: Activated::class)]
    case Active = 'active';
}
