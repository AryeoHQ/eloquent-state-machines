<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Context;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;
use Throwable;

final class ActivateUniquely extends Trigger implements ShouldBeUnique
{
    public const string FAILED = self::class.'::failed';

    #[Target]
    public readonly User $user;

    public function handle(): void
    {
        $this->user->forceFill([
            'activated_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Context::add(self::FAILED, $exception);
    }
}
