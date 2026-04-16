<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Illuminate\Support\Facades\Context;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\Status\Triggers\Exceptions\Unprocessable;
use Tests\Fixtures\Support\Users\User;
use Throwable;

final class WithSucceededAndFailed extends Trigger
{
    public const string SUCCEEDED = self::class.'::succeeded';

    public const string FAILED = self::class.'::failed';

    #[Target]
    public readonly User $user;

    public function handle(): void
    {
        throw new Unprocessable;
    }

    public function succeeded(): void
    {
        Context::push(Trigger::class, self::SUCCEEDED);
    }

    public function failed(Throwable $exception): void
    {
        Context::push(Trigger::class, self::FAILED);
    }
}
