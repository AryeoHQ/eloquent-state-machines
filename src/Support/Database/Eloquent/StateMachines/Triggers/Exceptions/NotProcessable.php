<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Exceptions;

use Illuminate\Support\Stringable;
use LogicException;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;

class NotProcessable extends LogicException
{
    private Stringable $template { get => str('[%s] is not processable. Ensure a `handle()` method is defined.'); }

    public function __construct(Trigger $trigger)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [class_basename($trigger::class)])->toString()
        );
    }
}
