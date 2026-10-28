<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Tests\Fixtures\Support\Users\User;

class TriggerInheritsIo extends AbstractTriggerPerformsIo
{
    #[Target]
    public readonly User $user;
}
