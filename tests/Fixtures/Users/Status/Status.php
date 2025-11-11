<?php

declare(strict_types=1);

namespace Tests\Fixtures\Users\Status;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tests\Fixtures\Users\Status\Events\Activated;
use Tests\Fixtures\Users\Status\Events\Activating;
use Tests\Fixtures\Users\Status\Events\Deactivated;
use Tests\Fixtures\Users\Status\Events\Deactivating;
use Tests\Fixtures\Users\Status\Events\Registered;
use Tests\Fixtures\Users\Status\Events\Registering;
use Tests\Fixtures\Users\Status\Events\Suspended;
use Tests\Fixtures\Users\Status\Events\Suspending;
use Tests\Fixtures\Users\Status\Triggers\Activate;
use Tests\Fixtures\Users\Status\Triggers\Deactivate;
use Tests\Fixtures\Users\Status\Triggers\Suspend;

/**
 * @method \Tests\Fixtures\Users\Status\Triggers\Activate activate()
 * @method \Tests\Fixtures\Users\Status\Triggers\Suspend suspend(?\Carbon\Carbon $at = null)
 * @method \Tests\Fixtures\Users\Status\Triggers\Deactivate deactivate()
 */
enum Status: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: Registering::class, after: Registered::class)]
    #[Transition(to: self::Activated, using: Activate::class)]
    #[Transition(to: self::Suspended, using: Suspend::class)]
    case Registered = 'registered';

    #[Events(before: Activating::class, after: Activated::class)]
    #[Transition(to: self::Deactivated, using: Deactivate::class)]
    case Activated = 'activated';

    #[Events(before: Deactivating::class, after: Deactivated::class)]
    case Deactivated = 'deactivated';

    #[Events(before: Suspending::class, after: Suspended::class)]
    case Suspended = 'suspended';
}
