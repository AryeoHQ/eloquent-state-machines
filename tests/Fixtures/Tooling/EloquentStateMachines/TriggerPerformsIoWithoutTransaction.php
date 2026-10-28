<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Illuminate\Support\Facades\Http;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\WithoutTransaction;
use Tests\Fixtures\Support\Users\User;

#[WithoutTransaction]
class TriggerPerformsIoWithoutTransaction extends Trigger
{
    #[Target]
    public readonly User $user;

    public function handle(): void
    {
        Http::post('https://example.com/users', ['id' => $this->user->id]);
    }
}
