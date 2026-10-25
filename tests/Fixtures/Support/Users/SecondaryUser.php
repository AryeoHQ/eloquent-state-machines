<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users;

/**
 * @property (\Tests\Fixtures\Support\Users\Status\Status & \Support\Database\Eloquent\StateMachines\StateMachine) $status
 *
 * @phpstan-property \Support\Database\Eloquent\StateMachines\StateMachine<\Tests\Fixtures\Support\Users\Status\Status> $status
 */
class SecondaryUser extends User
{
    protected $connection = 'secondary';

    protected $table = 'users';
}
