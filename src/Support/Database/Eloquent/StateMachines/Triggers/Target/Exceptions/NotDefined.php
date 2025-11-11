<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Target\Exceptions;

use Illuminate\Support\Stringable;
use RuntimeException;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;

class NotDefined extends RuntimeException
{
    private Stringable $template { get => str('[%s] is missing a property annotated with [%s].'); }

    public function __construct(Trigger $trigger)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [$trigger::class, Target::class])->toString(),
        );
    }
}
