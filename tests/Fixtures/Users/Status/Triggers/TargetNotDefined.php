<?php

declare(strict_types=1);

namespace Tests\Fixtures\Users\Status\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Users\User;

class TargetNotDefined extends Trigger
{
    protected readonly User $user;

    public function handle(): void {}

    public function allowed(): bool
    {
        return true;
    }
}
