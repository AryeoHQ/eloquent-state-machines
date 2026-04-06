<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Contracts;

use Support\Actions\Contracts\Action;

interface Trigger extends Action
{
    public function allowed(): bool;

    public function blocked(): bool;
}
