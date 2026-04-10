<?php

use Tooling\Actions\Rector\Rules\ActionMustBeFinal;
use Tooling\Actions\Rector\Rules\ActionMustDefineHandleMethod;

return [
    ActionMustBeFinal::class => [
        __DIR__.'/../../src/Support/Database/Eloquent/StateMachines/Triggers/Trigger.php',
    ],
    ActionMustDefineHandleMethod::class => [
        __DIR__.'/../../src/Support/Database/Eloquent/StateMachines/Triggers/Trigger.php',
    ],
];
