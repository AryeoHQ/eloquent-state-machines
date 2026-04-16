<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Middleware;

use Support\Database\Eloquent\StateMachines\Triggers\Trigger;

class ThroughLifecycle
{
    public function handle(Trigger $trigger, callable $next): mixed
    {
        return $trigger->lifecycle(fn () => $next($trigger));
    }
}
