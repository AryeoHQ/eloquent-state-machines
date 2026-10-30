<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tests\Fixtures\Support\Users\Status\Events\States\Activated;
use Tests\Fixtures\Support\Users\Status\Events\States\Activating;
use Tests\Fixtures\Support\Users\Status\Events\States\Deactivated;
use Tests\Fixtures\Support\Users\Status\Events\States\Deactivating;
use Tests\Fixtures\Support\Users\Status\Events\States\Registered;
use Tests\Fixtures\Support\Users\Status\Events\States\Registering;
use Tests\Fixtures\Support\Users\Status\Events\States\Suspended;
use Tests\Fixtures\Support\Users\Status\Events\States\Suspending;
use Tests\Fixtures\Support\Users\Status\Events\Transitions\Approved;
use Tests\Fixtures\Support\Users\Status\Events\Transitions\Approving;
use Tests\Fixtures\Support\Users\Status\Triggers\Activate;
use Tests\Fixtures\Support\Users\Status\Triggers\Deactivate;
use Tests\Fixtures\Support\Users\Status\Triggers\Ping;

/**
 * @method \Tests\Fixtures\Support\Users\Status\Triggers\Ping ping()
 */
enum StationaryTransitionEvents: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: Registering::class, after: Registered::class)]
    #[Transition(to: self::Registered, using: Ping::class, events: new Events(before: Approving::class, after: Approved::class))]
    case Registered = 'registered';

    #[Events(before: Activating::class, after: Activated::class)]
    #[Transition(self::Active, Activate::class, new Events(Suspending::class, Suspended::class))]
    case Active = 'active';

    #[Transition(to: StationaryTransitionEvents::Inactive, using: Deactivate::class, events: new Events(before: Deactivating::class, after: Deactivated::class))]
    case Inactive = 'inactive';
}
