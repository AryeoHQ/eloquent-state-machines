<?php

use Tooling\Actions\Rector\Rules\ActionCannotUseQueueable;
use Tooling\Actions\Rector\Rules\ActionMustBeFinal;
use Tooling\Actions\Rector\Rules\ActionMustDefineHandleMethod;
use Tooling\Actions\Rector\Rules\ActionMustUseAsAction;
use Tooling\Actions\Rector\Rules\AsActionMustImplementAction;

return [
    ActionMustDefineHandleMethod::class => [
        __DIR__.'/../../src/Support/Database/Eloquent/StateMachines/Triggers/Trigger.php'
    ],
];
