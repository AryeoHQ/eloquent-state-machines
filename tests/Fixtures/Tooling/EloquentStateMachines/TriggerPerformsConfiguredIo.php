<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\User;
use Tests\Fixtures\Tooling\EloquentStateMachines\ExternalService\Client;

class TriggerPerformsConfiguredIo extends Trigger
{
    #[Target]
    public readonly User $user;

    public function handle(Client $client): void
    {
        Client::connect();
        $client->send($this->user->id);
    }
}
