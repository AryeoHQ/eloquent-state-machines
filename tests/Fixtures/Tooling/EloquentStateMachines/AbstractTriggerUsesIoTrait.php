<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Support\Database\Eloquent\StateMachines\Triggers\Trigger;

abstract class AbstractTriggerUsesIoTrait extends Trigger
{
    use PerformsIo;
}
