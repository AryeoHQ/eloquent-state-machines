<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Triggers;

use Illuminate\Queue\ManuallyFailedException;
use Illuminate\Support\Facades\Context;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Support\Users\User;

final class CatchesInnerManualFail extends Trigger
{
    public const string CAUGHT = self::class.'::caught';

    #[Target]
    public readonly User $user;

    public readonly User $inner;

    public function __construct(User $inner)
    {
        $this->inner = $inner;
    }

    public function handle(): void
    {
        $this->user->forceFill([
            'activated_at' => now(),
        ]);

        try {
            WithManualFail::make()->to(Status::Activated)->on($this->inner)->now();
        } catch (ManuallyFailedException) {
            Context::push(Trigger::class, self::CAUGHT);
        }
    }
}
