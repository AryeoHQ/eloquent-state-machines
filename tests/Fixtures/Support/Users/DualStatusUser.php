<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users;

use Tests\Fixtures\Support\Users\Status\Status;

/**
 * @property (\Tests\Fixtures\Support\Users\Status\Status & \Support\Database\Eloquent\StateMachines\StateMachine) $status
 *
 * @phpstan-property \Support\Database\Eloquent\StateMachines\StateMachine<\Tests\Fixtures\Support\Users\Status\Status> $status
 *
 * @property (\Tests\Fixtures\Support\Users\Status\Status & \Support\Database\Eloquent\StateMachines\StateMachine) $secondary_status
 *
 * @phpstan-property \Support\Database\Eloquent\StateMachines\StateMachine<\Tests\Fixtures\Support\Users\Status\Status> $secondary_status
 */
class DualStatusUser extends User
{
    protected $table = 'users';

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'secondary_status' => Status::class,
        ];
    }
}
