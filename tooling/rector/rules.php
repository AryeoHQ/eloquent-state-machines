<?php

use Tooling\EloquentStateMachines\Rector\Rules\AddStateMachineablePropertiesToModelDocBlocks;
use Tooling\EloquentStateMachines\Rector\Rules\AddTriggerMethodsToStateMachineableDocBlocks;
use Tooling\EloquentStateMachines\Rector\Rules\TriggerCannotUseSerializesModels;

return [
    AddTriggerMethodsToStateMachineableDocBlocks::class,
    AddStateMachineablePropertiesToModelDocBlocks::class,
    TriggerCannotUseSerializesModels::class,
];
