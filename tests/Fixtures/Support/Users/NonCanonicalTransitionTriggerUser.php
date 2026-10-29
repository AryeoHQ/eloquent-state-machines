<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users;

use Tests\Fixtures\Tooling\EloquentStateMachines\NonCanonicalTransitionTrigger;

/**
 * @property (\Tests\Fixtures\Tooling\EloquentStateMachines\NonCanonicalTransitionTrigger & \Support\Database\Eloquent\StateMachines\StateMachine) $status
 *
 * @phpstan-property \Support\Database\Eloquent\StateMachines\StateMachine<\Tests\Fixtures\Tooling\EloquentStateMachines\NonCanonicalTransitionTrigger> $status
 */
class NonCanonicalTransitionTriggerUser extends User
{
    protected $table = 'users';

    protected $attributes = [
        'status' => NonCanonicalTransitionTrigger::Registered,
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'status' => NonCanonicalTransitionTrigger::class,
        ];
    }
}
