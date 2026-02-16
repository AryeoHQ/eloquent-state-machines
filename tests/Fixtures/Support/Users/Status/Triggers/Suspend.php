<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Carbon\Carbon;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;

class Suspend extends Trigger
{
    #[Target]
    protected readonly User $user;

    protected null|Carbon $at = null;

    public function __construct(null|Carbon $at = null)
    {
        $this->at = $at;
    }

    public function handle(): void
    {
        $this->user->forceFill([
            'suspended_at' => $this->at ?? now(),
        ]);
    }

    public function allowed(): bool
    {
        return true;
    }
}
