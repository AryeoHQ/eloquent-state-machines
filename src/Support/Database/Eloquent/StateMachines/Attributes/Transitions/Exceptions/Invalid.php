<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Attributes\Transitions\Exceptions;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Stringable;
use RuntimeException;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;

class Invalid extends RuntimeException
{
    private Stringable $template { get => str('Invalid transition from [%s] to [%s] on [%s].'); }

    public function __construct(Model $model, StateMachineable&BackedEnum $status, string $field)
    {
        parent::__construct(
            $this->template->replaceArray(
                '%s', [$model->$field->enum->name, $status->name, class_basename($model::class)]
            )->toString()
        );
    }
}
