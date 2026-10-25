<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Events;

use Tests\Fixtures\Support\Users\User;

class Updating
{
    public readonly User $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }
}
