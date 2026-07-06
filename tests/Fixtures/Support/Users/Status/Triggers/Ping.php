<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;

final class Ping extends Trigger
{
    #[Target]
    public readonly User $user;

    public function handle(): void
    {
        $this->user->forceFill([
            'updated_at' => now()->addMinute(),
        ]);
    }
}
