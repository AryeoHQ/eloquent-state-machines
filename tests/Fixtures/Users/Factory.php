<?php

declare(strict_types=1);

namespace Tests\Fixtures\Users;

use Tests\Fixtures\Users\Status\Status;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<User>
 */
class Factory extends \Illuminate\Database\Eloquent\Factories\Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [];
    }

    public function registered(): static
    {
        return $this->state(
            fn () => ['status' => Status::Registered]
        );
    }

    public function activated(): static
    {
        return $this->state(
            fn () => ['status' => Status::Activated]
        );
    }

    public function trashed(): static
    {
        return $this->state(
            fn () => ['deleted_at' => now()]
        );
    }
}
