<?php

declare(strict_types=1);

namespace Tests\Fixtures\Users\Status\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;

class TargetNotModel extends Trigger
{
    #[Target]
    protected readonly int $id;

    public function handle(): void {}

    public function allowed(): bool
    {
        return true;
    }
}
