<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\PhpStan\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;

class HandleNotDefined extends Trigger
{
    #[Target]
    protected readonly User $user;

    public function allowed(): bool
    {
        return true;
    }
}
