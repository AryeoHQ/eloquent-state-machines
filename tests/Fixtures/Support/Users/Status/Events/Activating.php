<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Events;

use Tests\Fixtures\Support\Users\User;

class Activating
{
    public readonly User $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }
}
