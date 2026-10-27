<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users;

use Tests\Fixtures\Support\Users\Status\Status;

/**
 * @property (\Tests\Fixtures\Support\Users\Status\Status & \Support\Database\Eloquent\StateMachines\StateMachine) $legacy_status
 * @property (\Tests\Fixtures\Support\Users\Status\Status & \Support\Database\Eloquent\StateMachines\StateMachine) $status
 *
 * @phpstan-property \Support\Database\Eloquent\StateMachines\StateMachine<\Tests\Fixtures\Support\Users\Status\Status> $legacy_status
 * @phpstan-property \Support\Database\Eloquent\StateMachines\StateMachine<\Tests\Fixtures\Support\Users\Status\Status> $status
 */
class LegacyStatusUser extends User
{
    protected $table = 'users';

    protected function casts(): array
    {
        return [
            'legacy_status' => Status::class,
            ...parent::casts(),
        ];
    }
}
