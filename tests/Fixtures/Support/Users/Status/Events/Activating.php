<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users\Status\Events;

use Illuminate\Contracts\Config\Repository;
use Tests\Fixtures\Support\Users\User;

class Activating
{
    public readonly User $model;

    public readonly Repository $config;

    public function __construct(User $model, Repository $config)
    {
        $this->model = $model;
        $this->config = $config;
    }
}
