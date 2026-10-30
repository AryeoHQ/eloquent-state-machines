<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions;

use BackedEnum;
use Illuminate\Support\Stringable;
use LogicException;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;

class Stationary extends LogicException
{
    private Stringable $template { get => str('Stationary [%s] transition on [%s] cannot define events'); }

    public function __construct(string $trigger, StateMachineable&BackedEnum $case)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [class_basename($trigger), $case->name])->toString()
        );
    }
}
