<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Provides;

use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions\NotDefined;

trait DefinesEvents
{
    public function events(): Events
    {
        $reflection = new \ReflectionEnumBackedCase($this, $this->name);

        return with(
            collect($reflection->getAttributes(Events::class))->map->newInstance()->first(),
            fn (null|Events $events): Events => throw_unless($events, NotDefined::class, $this)
        );
    }
}
