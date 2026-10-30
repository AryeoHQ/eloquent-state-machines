<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tests\Fixtures\Support\Users\Status\Events\States\Activated;
use Tests\Fixtures\Support\Users\Status\Events\States\Deactivated;
use Tests\Fixtures\Support\Users\Status\Events\States\Deactivating;
use Tests\Fixtures\Support\Users\Status\Triggers\Activate;

/**
 * @method \Tests\Fixtures\Support\Users\Status\Triggers\Activate activate()
 */
enum DuplicateEventInDeclaration: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: Deactivating::class, after: Deactivated::class)]
    #[Transition(to: self::Active, using: Activate::class)]
    case Inactive = 'inactive';

    #[Events(before: Activated::class, after: Activated::class)]
    case Active = 'active';
}
