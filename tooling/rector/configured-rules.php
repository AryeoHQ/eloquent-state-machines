<?php

use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tooling\Rector\Rules\AddInterfaceByTrait;
use Tooling\Rector\Rules\AddTraitByInterface;

return [
    AddInterfaceByTrait::class => [
        ManagesState::class => StateMachineable::class,
    ],
    AddTraitByInterface::class => [
        StateMachineable::class => ManagesState::class,
    ],
];
