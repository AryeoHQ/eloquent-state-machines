<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Illuminate\Support\Facades\Http;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;

abstract class AbstractTriggerPerformsIo extends Trigger
{
    public function handle(): void
    {
        Http::post('https://example.com/users');
    }
}
