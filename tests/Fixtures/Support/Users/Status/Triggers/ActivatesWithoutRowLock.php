<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\WithoutRowLock;

#[WithoutRowLock]
abstract class ActivatesWithoutRowLock extends Trigger {} // @phpstan-ignore Action.final.required, Action.handle.required
