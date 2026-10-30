<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Tests\Fixtures\Support\Users\Status\Events\States\Activated;
use Tests\Fixtures\Support\Users\Status\Events\States\Activating;

final class EventNames
{
    public const string ACTIVATING = Activating::class;

    public const string ACTIVATED = Activated::class;
}
