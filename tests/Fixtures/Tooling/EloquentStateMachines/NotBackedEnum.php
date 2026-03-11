<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;

enum NotBackedEnum implements StateMachineable
{
    use ManagesState;

    case Active;
    case Inactive;
}
