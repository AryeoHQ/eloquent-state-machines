<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Target\Exceptions;

use Illuminate\Support\Stringable;
use LogicException;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;

class MultipleDefined extends LogicException
{
    private Stringable $template { get => str('[%s] can only have one property annotated with [%s].'); }

    public function __construct(Trigger $trigger)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [class_basename($trigger::class), class_basename(Target::class)])->toString(),
        );
    }
}
