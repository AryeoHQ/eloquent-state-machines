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
use Tests\Fixtures\Support\Users\Status\Events\Transitions\Reminded;
use Tests\Fixtures\Support\Users\Status\Events\Transitions\Reminding;
use Tests\Fixtures\Support\Users\Status\Triggers\Approve;
use Tests\Fixtures\Support\Users\Status\Triggers\Remind;

class_alias(Approve::class, AliasedApprove::class);
class_alias(Remind::class, AliasedRemind::class);

enum NonCanonicalTransitionTrigger: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: Registering::class, after: Registered::class)]
    #[Transition(to: self::Registered, using: AliasedRemind::class, before: Reminding::class, after: Reminded::class)]
    #[Transition(to: self::Registered, using: '\Tests\Fixtures\Support\Users\Status\Triggers\Ping', before: Suspending::class, after: Suspended::class)]
    #[Transition(to: self::Activated, using: AliasedApprove::class, before: Approving::class, after: Approved::class)]
    #[Transition(to: self::Activated, using: '\Tests\Fixtures\Support\Users\Status\Triggers\Activate', before: Deactivating::class, after: Deactivated::class)]
    case Registered = 'registered';

    #[Events(before: Activating::class, after: Activated::class)]
    case Activated = 'activated';
}
