<?php

declare(strict_types=1);

namespace Tests\Fixtures\Users\Status\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Tests\Fixtures\Users\User;

class NotATrigger
{
    #[Target]
    public readonly User $user;

    public function allowed(): bool
    {
        return $this->user->is_not_trashed;
    }

    public function handle(): void
    {
        $this->user->forceFill([
            'activated_at' => now(),
        ]);
    }
}
