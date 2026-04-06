<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Illuminate\Support\Carbon;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;

final class Deactivate extends Trigger
{
    #[Target]
    protected readonly User $user;

    public function handle(Carbon $at): void
    {
        $this->user->forceFill([
            'deactivated_at' => $at,
        ]);
    }
}
