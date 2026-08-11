<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Illuminate\Queue\Middleware\WithoutOverlapping as WithoutOverlappingMiddleware;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;

final class WithMiddleware extends Trigger
{
    public const KEY = 'user-status';

    #[Target]
    public readonly User $user;

    public function __construct()
    {
        $this->middleware = [new WithoutOverlappingMiddleware(self::KEY)];
    }

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
