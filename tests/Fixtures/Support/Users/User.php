<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\Fixtures\Support\Users\Factories\Factory;
use Tests\Fixtures\Support\Users\Status\Status;

/**
 * @property bool $is_trashed
 * @property bool $is_not_trashed
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon|null $deactivated_at
 * @property string|null $suspended_at
 * @property (\Tests\Fixtures\Support\Users\Status\Status & \Support\Database\Eloquent\StateMachines\StateMachine) $status
 *
 * @phpstan-property \Support\Database\Eloquent\StateMachines\StateMachine<\Tests\Fixtures\Support\Users\Status\Status> $status
 */
#[UseFactory(Factory::class)]
class User extends Model
{
    /** @use HasFactory<Factory>  */
    use HasFactory;

    use SoftDeletes;

    protected $attributes = [
        'status' => Status::Registered,
    ];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function markSuspended(): static
    {
        return $this->forceFill([
            'suspended_at' => now(),
        ]);
    }

    /** @return Attribute<bool, never> */
    protected function isTrashed(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => ! $this->is_not_trashed,
        );
    }

    /** @return Attribute<bool, never> */
    protected function isNotTrashed(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes): bool => is_null(data_get($attributes, 'deleted_at')),
        );
    }
}
