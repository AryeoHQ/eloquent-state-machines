<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Illuminate\Support\Facades\Context;
use Support\Database\Eloquent\StateMachines\Triggers\Phases\Phase;
use Support\Database\Eloquent\StateMachines\Triggers\Phases\TransitionDuring;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;

#[TransitionDuring(Phase::Before)]
final class ActivateBeforeTransition extends Trigger
{
    #[Target]
    public readonly User $user;

    public function allowed(): bool
    {
        return $this->user->is_not_trashed;
    }

    public function handle(): void
    {
        Context::add(self::class, $this->user->getRawOriginal('status'));

        $this->user->forceFill([
            'activated_at' => now(),
        ]);
    }
}
