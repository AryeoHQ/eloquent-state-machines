<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Stringable;
use LogicException;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;

class StateChangedOutsideTrigger extends LogicException
{
    private Stringable $template { get => str('[%s] on [%s] can only change through a [%s].'); }

    public function __construct(Model $model, string $field)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [$field, class_basename($model::class), class_basename(Trigger::class)])->toString()
        );
    }
}
