<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Plan;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tests\Fixtures\Support\Users\Plan\Triggers\Upgrade;
use Tests\Fixtures\Support\Users\Status\Events\Activated;
use Tests\Fixtures\Support\Users\Status\Events\Activating;
use Tests\Fixtures\Support\Users\Status\Events\Registered;
use Tests\Fixtures\Support\Users\Status\Events\Registering;

/**
 * @method \Tests\Fixtures\Support\Users\Plan\Triggers\Upgrade upgrade(string $reason)
 */
enum Plan: string implements StateMachineable
{
    use ManagesState;

    #[Events(before: Registering::class, after: Registered::class)]
    #[Transition(to: self::Paid, using: Upgrade::class)]
    case Free = 'free';

    #[Events(before: Activating::class, after: Activated::class)]
    case Paid = 'paid';
}
