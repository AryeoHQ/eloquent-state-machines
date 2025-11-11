<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions;

use BackedEnum;
use Illuminate\Support\Stringable;
use LogicException;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;

class NotDefined extends LogicException
{
    private Stringable $template { get => str('Events must be defined on case [%s].'); }

    public function __construct(StateMachineable&BackedEnum $status)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [$status->name])->toString()
        );
    }
}
