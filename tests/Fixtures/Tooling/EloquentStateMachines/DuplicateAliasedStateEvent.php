<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tests\Fixtures\Support\Users\Status\Events\States\Activated;
use Tests\Fixtures\Support\Users\Status\Events\States\Activating;
use Tests\Fixtures\Support\Users\Status\Events\States\Deactivating;

class_alias(Activated::class, AliasedActivated::class);

/**
 * @method \Tests\Fixtures\Support\Users\Status\Triggers\Activate activate()
 */
enum DuplicateAliasedStateEvent: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: Activating::class, after: Activated::class)]
    case Active = 'active';

    #[Events(before: Deactivating::class, after: AliasedActivated::class)]
    case Inactive = 'inactive';
}
