<?php

declare(strict_types=1);

namespace Tests\Fixtures\Users\Status;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tests\Fixtures\Users\Status\Events\Registered;
use Tests\Fixtures\Users\Status\Events\Registering;
use Tests\Fixtures\Users\Status\Triggers\Activate;

/**
 * @method \Tests\Fixtures\Users\Status\Triggers\Activate activate()
 */
enum DuplicateTrigger: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: Registering::class, after: Registered::class)]
    #[Transition(to: self::Activated, using: Activate::class)]
    #[Transition(to: self::Deactivated, using: Activate::class)]
    case Registered = 'registered';

    case Activated = 'activated';

    case Deactivated = 'deactivated';
}
