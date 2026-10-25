<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users;

use Tests\Fixtures\Support\Users\Plan\Plan;

/**
 * @property (\Tests\Fixtures\Support\Users\Status\Status & \Support\Database\Eloquent\StateMachines\StateMachine) $status
 *
 * @phpstan-property \Support\Database\Eloquent\StateMachines\StateMachine<\Tests\Fixtures\Support\Users\Status\Status> $status
 *
 * @property (\Tests\Fixtures\Support\Users\Plan\Plan & \Support\Database\Eloquent\StateMachines\StateMachine) $plan
 *
 * @phpstan-property \Support\Database\Eloquent\StateMachines\StateMachine<\Tests\Fixtures\Support\Users\Plan\Plan> $plan
 */
class PlanUser extends User
{
    protected $table = 'users';

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'plan' => Plan::class,
        ];
    }
}
