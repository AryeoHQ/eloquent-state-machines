<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Illuminate\Support\Facades\Http;

class NotATriggerPerformsIo
{
    public function handle(): void
    {
        Http::post('https://example.com/users');
    }
}
