<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tests\Fixtures\Support\Users\Status\Events\Activated;
use Tests\Fixtures\Support\Users\Status\Events\Activating;
use Tests\Fixtures\Support\Users\Status\Triggers\Activate;

/**
 * @method \Tests\Fixtures\Support\Users\Status\Triggers\Activate activate()
 */
enum DuplicateTransitionTrigger: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: Activating::class, after: Activated::class)]
    #[Transition(to: self::Deactivated, using: Activate::class)]
    #[Transition(to: self::Pending, using: Activate::class)]
    case Active = 'active';

    case Deactivated = 'deactivated';

    case Pending = 'pending';
}
