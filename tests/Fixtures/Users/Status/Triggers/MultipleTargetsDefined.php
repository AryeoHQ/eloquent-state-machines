<?php

declare(strict_types=1);

namespace Tests\Fixtures\Users\Status\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Users\User;

class MultipleTargetsDefined extends Trigger
{
    #[Target]
    protected readonly User $one;

    #[Target]
    protected readonly User $two;

    public function handle(): void {}

    public function allowed(): bool
    {
        return true;
    }
}
