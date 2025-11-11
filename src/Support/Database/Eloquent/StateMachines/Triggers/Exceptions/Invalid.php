<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Exceptions;

use BackedEnum;
use Illuminate\Support\Stringable;
use RuntimeException;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachine;

class Invalid extends RuntimeException
{
    private Stringable $template { get => str('[%s] is not an available trigger on [%s]'); }

    public function __construct(string $trigger, StateMachine&BackedEnum $enum)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [$trigger, $enum->name])->toString()
        );
    }
}
