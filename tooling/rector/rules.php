<?php

use Tooling\EloquentStateMachines\Rector\Rules\AddStateMachineablePropertiesToModelDocBlocks;
use Tooling\EloquentStateMachines\Rector\Rules\AddTriggerMethodsToStateMachineableDocBlocks;

return [
    AddTriggerMethodsToStateMachineableDocBlocks::class,
    AddStateMachineablePropertiesToModelDocBlocks::class,
];
