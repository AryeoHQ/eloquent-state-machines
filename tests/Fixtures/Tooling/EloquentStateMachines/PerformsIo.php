<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Illuminate\Support\Facades\Http;

trait PerformsIo
{
    protected function send(): void
    {
        Http::post('https://example.com/users');
    }
}
