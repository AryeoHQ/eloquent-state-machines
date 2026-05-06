<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines;

use Illuminate\Database\Eloquent\Model;

class ModelWithoutStateMachineableCasts extends Model
{
    protected function casts(): array
    {
        return [
            'name' => 'string',
        ];
    }
}
