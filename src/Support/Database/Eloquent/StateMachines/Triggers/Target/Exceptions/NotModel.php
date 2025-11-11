<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Target\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Stringable;
use LogicException;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;
use Support\Database\Eloquent\StateMachines\Triggers\Target\Target;

class NotModel extends LogicException
{
    private Stringable $template { get => str('[%s] property annotated with [%s] must be a [%s].'); }

    public function __construct(Trigger $trigger)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [$trigger::class, Target::class, Model::class])->toString(),
        );
    }
}
