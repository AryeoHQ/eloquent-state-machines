<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Tests\Fixtures\Support\Users\User;

final class InheritsWithoutRowLock extends ActivatesWithoutRowLock
{
    #[Target]
    public readonly User $user;

    public function handle(): void {}
}
