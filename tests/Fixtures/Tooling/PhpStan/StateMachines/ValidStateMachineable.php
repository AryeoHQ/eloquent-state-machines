<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\PhpStan\StateMachines;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tests\Fixtures\Support\Users\Status\Events\Activated;
use Tests\Fixtures\Support\Users\Status\Events\Activating;
use Tests\Fixtures\Support\Users\Status\Events\Deactivated;
use Tests\Fixtures\Support\Users\Status\Events\Deactivating;
use Tests\Fixtures\Support\Users\Status\Triggers\Activate;
use Tests\Fixtures\Support\Users\Status\Triggers\Deactivate;

enum ValidStateMachineable: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: Activating::class, after: Activated::class)]
    #[Transition(to: self::Deactivated, using: Deactivate::class)]
    case Active = 'active';

    #[Events(before: Deactivating::class, after: Deactivated::class)]
    #[Transition(to: self::Active, using: Activate::class)]
    case Deactivated = 'deactivated';
}
