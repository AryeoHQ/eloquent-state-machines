<?php

declare(strict_types=1);

namespace Tests\Fixtures\Users\Status;

use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;

enum EventsNotDefined: string implements StateMachineable
{
    use ManagesState;

    case Pending = 'pending';
}
