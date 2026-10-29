<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Provides;

use ReflectionAttribute;
use ReflectionEnumBackedCase;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Declarations;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions\NotDefined;

trait DefinesEvents
{
    public function events(): Events
    {
        Declarations::preventReuse(static::class);

        $reflection = new ReflectionEnumBackedCase($this, $this->name);

        return with(
            collect($reflection->getAttributes(Events::class))->map(
                fn (ReflectionAttribute $attribute): Events => $attribute->newInstance()
            )->first(),
            fn (null|Events $events): Events => throw_unless($events, NotDefined::class, $this)
        );
    }
}
