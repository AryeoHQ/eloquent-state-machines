<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Illuminate\Queue\SerializesModels;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;

final class WithSerializedModel extends Trigger
{
    use SerializesModels;

    #[Target]
    protected readonly User $user;

    public function handle(): void
    {
        $this->user->forceFill([
            'activated_at' => now(),
        ]);
    }
}
