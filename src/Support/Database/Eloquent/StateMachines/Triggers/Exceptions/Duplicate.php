<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Exceptions;

use BackedEnum;
use Illuminate\Support\Stringable;
use LogicException;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachine;

class Duplicate extends LogicException
{
    private Stringable $template { get => str('Multiple [%s] triggers defined on [%s]'); }

    public function __construct(string $trigger, StateMachine&BackedEnum $enum)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [$trigger, $enum->name])->toString()
        );
    }
}
