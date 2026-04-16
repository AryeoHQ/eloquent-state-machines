<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers\Middleware;

use Illuminate\Support\Facades\Context;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;

class RecordExecution
{
    public const string EXECUTED = self::class.'::executed';

    public function handle(Trigger $trigger, callable $next): mixed
    {
        Context::push(Trigger::class, self::EXECUTED);

        return $next($trigger);
    }
}
