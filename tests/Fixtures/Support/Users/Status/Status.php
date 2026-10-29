<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status;

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
use Tests\Fixtures\Support\Users\Status\Events\Transitions\Reminded;
use Tests\Fixtures\Support\Users\Status\Events\Transitions\Reminding;
use Tests\Fixtures\Support\Users\Status\Triggers\Activate;
use Tests\Fixtures\Support\Users\Status\Triggers\Approve;
use Tests\Fixtures\Support\Users\Status\Triggers\Deactivate;
use Tests\Fixtures\Support\Users\Status\Triggers\Onboard;
use Tests\Fixtures\Support\Users\Status\Triggers\Ping;
use Tests\Fixtures\Support\Users\Status\Triggers\Remind;
use Tests\Fixtures\Support\Users\Status\Triggers\Suspend;

/**
 * @method \Tests\Fixtures\Support\Users\Status\Triggers\Onboard onboard()
 * @method \Tests\Fixtures\Support\Users\Status\Triggers\Ping ping()
 * @method \Tests\Fixtures\Support\Users\Status\Triggers\Remind remind()
 * @method \Tests\Fixtures\Support\Users\Status\Triggers\Activate activate()
 * @method \Tests\Fixtures\Support\Users\Status\Triggers\Approve approve()
 * @method \Tests\Fixtures\Support\Users\Status\Triggers\Suspend suspend(?\Carbon\Carbon $at = null)
 * @method \Tests\Fixtures\Support\Users\Status\Triggers\Deactivate deactivate()
 */
enum Status: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: Registering::class, after: Registered::class)]
    #[Transition(to: self::Registered, using: Onboard::class)]
    #[Transition(to: self::Registered, using: Ping::class)]
    #[Transition(to: self::Registered, using: Remind::class, before: Reminding::class, after: Reminded::class)]
    #[Transition(to: self::Activated, using: Activate::class)]
    #[Transition(to: self::Activated, using: Approve::class, before: Approving::class, after: Approved::class)]
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
