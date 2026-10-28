<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\WithoutRowLock;
use Tests\Fixtures\Support\Users\User;

#[WithoutRowLock]
final class ActivateWithoutRowLock extends Trigger
{
    #[Target]
    public readonly User $user;

    public function handle(): void
    {
        $this->user->forceFill([
            'activated_at' => now(),
        ]);
    }
}
