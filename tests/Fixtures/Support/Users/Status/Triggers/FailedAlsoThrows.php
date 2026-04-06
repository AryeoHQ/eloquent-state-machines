<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use RuntimeException;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\Status\Triggers\Exceptions\Unprocessable;
use Tests\Fixtures\Support\Users\User;
use Throwable;

final class FailedAlsoThrows extends Trigger
{
    #[Target]
    public readonly User $user;

    public function handle(): void
    {
        throw new Unprocessable;
    }

    public function failed(Throwable $exception): void
    {
        throw new RuntimeException('failed() blew up');
    }
}
