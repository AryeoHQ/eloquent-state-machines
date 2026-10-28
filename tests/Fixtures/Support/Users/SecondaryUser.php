<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Tests\Fixtures\Support\Users\Factories\Factory;

/**
 * @property (\Tests\Fixtures\Support\Users\Status\Status & \Support\Database\Eloquent\StateMachines\StateMachine) $status
 *
 * @phpstan-property \Support\Database\Eloquent\StateMachines\StateMachine<\Tests\Fixtures\Support\Users\Status\Status> $status
 */
#[UseFactory(Factory::class)]
class SecondaryUser extends User
{
    protected $connection = 'secondary';

    protected $table = 'users';
}
