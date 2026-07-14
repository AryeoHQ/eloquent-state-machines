<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Illuminate\Support\Facades\Context;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\Status\Triggers\Exceptions\Unprocessable;
use Tests\Fixtures\Support\Users\User;
use Throwable;

final class ThrowsExceptionAfterWrite extends Trigger
{
    public const string FAILED = self::class.'::failed';

    public const string ACTIVATED_AT = self::class.'::activated_at';

    #[Target]
    public readonly User $user;

    public function handle(): void
    {
        $this->user->forceFill([
            'activated_at' => now(),
        ])->save();

        throw new Unprocessable;
    }

    public function failed(Throwable $exception): void
    {
        Context::push(Trigger::class, self::FAILED);
        Context::add(self::ACTIVATED_AT, $this->user->activated_at);
    }
}
