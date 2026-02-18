<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\PhpStan\StateMachines;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Tests\Fixtures\Support\Users\Status\Events\Activated;
use Tests\Fixtures\Support\Users\Status\Events\Activating;

enum MissingManagesState: string implements StateMachineable
{
    #[Events(before: Activating::class, after: Activated::class)]
    case Active = 'active';
}
