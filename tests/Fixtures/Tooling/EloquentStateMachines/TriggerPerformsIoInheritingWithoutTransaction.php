<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Illuminate\Support\Facades\Http;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Tests\Fixtures\Support\Users\User;

class TriggerPerformsIoInheritingWithoutTransaction extends TriggerWithoutTransaction
{
    #[Target]
    public readonly User $user;

    public function handle(): void
    {
        Http::post('https://example.com/users', ['id' => $this->user->id]);
    }
}
