<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Provides;

use Illuminate\Support\Collection;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;

trait DefinesTransitions
{
    /**
     * @return Collection<array-key, Transition>
     */
    public function transitions(): Collection
    {
        $reflection = new \ReflectionEnumBackedCase($this, $this->name);

        return collect($reflection->getAttributes(Transition::class))->map->newInstance();
    }
}
