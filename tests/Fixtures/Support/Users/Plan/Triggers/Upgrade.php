<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Plan\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;

final class Upgrade extends Trigger
{
    #[Target]
    protected readonly User $user;

    protected string $reason;

    public function __construct(string $reason)
    {
        $this->reason = $reason;
    }

    public function handle(): void
    {
        $this->user->forceFill([
            'activated_at' => now(),
        ]);
    }
}
