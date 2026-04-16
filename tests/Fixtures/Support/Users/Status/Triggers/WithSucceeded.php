<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Illuminate\Support\Facades\Context;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;

final class WithSucceeded extends Trigger
{
    public const string SUCCEEDED = self::class.'::succeeded';

    #[Target]
    public readonly User $user;

    public function handle(): void
    {
        $this->user->forceFill([
            'activated_at' => now(),
        ]);
    }

    public function succeeded(): void
    {
        Context::push(Trigger::class, self::SUCCEEDED);
    }
}
