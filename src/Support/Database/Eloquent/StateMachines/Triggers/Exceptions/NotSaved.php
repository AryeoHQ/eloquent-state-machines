<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Stringable;
use RuntimeException;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;

class NotSaved extends RuntimeException
{
    private Stringable $template { get => str('[%s] did not save [%s] with key [%s] on [%s].'); }

    public function __construct(Trigger $trigger, Model $model, string $field)
    {
        parent::__construct(
            $this->template->replaceArray(
                '%s', [$trigger::class, class_basename($model::class), (string) ($model->getKey() ?? 'null'), $field]
            )->toString()
        );
    }
}
