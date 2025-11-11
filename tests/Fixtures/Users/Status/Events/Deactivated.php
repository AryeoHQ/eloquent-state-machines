<?php

declare(strict_types=1);

namespace Tests\Fixtures\Users\Status\Events;

use Illuminate\Database\Eloquent\Model;

class Deactivated
{
    public readonly Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }
}
