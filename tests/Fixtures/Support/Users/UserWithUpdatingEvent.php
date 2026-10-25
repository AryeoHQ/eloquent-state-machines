<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users;

use Tests\Fixtures\Support\Users\Events\Updating;

/**
 * @property (\Tests\Fixtures\Support\Users\Status\Status & \Support\Database\Eloquent\StateMachines\StateMachine) $status
 *
 * @phpstan-property \Support\Database\Eloquent\StateMachines\StateMachine<\Tests\Fixtures\Support\Users\Status\Status> $status
 */
class UserWithUpdatingEvent extends User
{
    protected $table = 'users';

    protected $dispatchesEvents = [
        'updating' => Updating::class,
    ];
}
