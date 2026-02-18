<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\PhpStan\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;

class MissingTarget extends Trigger
{
    protected readonly User $user;

    public function handle(): void {}

    public function allowed(): bool
    {
        return true;
    }
}
