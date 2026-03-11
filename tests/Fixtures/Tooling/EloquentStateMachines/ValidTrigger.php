<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;

class ValidTrigger extends Trigger
{
    #[Target]
    public readonly User $user;

    public function handle(): void {}

    public function allowed(): bool
    {
        return true;
    }
}
