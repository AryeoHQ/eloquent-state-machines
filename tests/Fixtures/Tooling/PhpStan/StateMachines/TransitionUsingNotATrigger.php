<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\PhpStan\StateMachines;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tests\Fixtures\Support\Users\Status\Events\Activated;
use Tests\Fixtures\Support\Users\Status\Events\Activating;
use Tests\Fixtures\Tooling\PhpStan\Triggers\NotATrigger;

enum TransitionUsingNotATrigger: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: Activating::class, after: Activated::class)]
    #[Transition(to: self::Inactive, using: NotATrigger::class)]
    case Active = 'active';

    #[Events(before: Activating::class, after: Activated::class)]
    case Inactive = 'inactive';
}
