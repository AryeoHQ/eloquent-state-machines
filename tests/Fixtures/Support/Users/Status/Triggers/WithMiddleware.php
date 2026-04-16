<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\Status\Triggers\Middleware\RecordExecution;
use Tests\Fixtures\Support\Users\User;

final class WithMiddleware extends Trigger
{
    /** @var array<mixed> */
    public $middleware = [RecordExecution::class];

    #[Target]
    public readonly User $user;

    public function handle(): void
    {
        $this->user->forceFill([
            'activated_at' => now(),
        ]);
    }
}
