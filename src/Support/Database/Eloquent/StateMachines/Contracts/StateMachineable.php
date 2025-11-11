<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Contracts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Support\Collection;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;

interface StateMachineable extends Castable, StateMachine
{
    public function events(): Events;

    /**
     * @return Collection<int, Transition>
     */
    public function transitions(): Collection;
}
