<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Exceptions;

use Illuminate\Support\Stringable;
use LogicException;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;

class NotFound extends LogicException
{
    private Stringable $template { get => str('The trigger [%s] was not found or does not implement [%s].'); }

    public function __construct(string $name)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [$name, class_basename(Trigger::class)])->toString()
        );
    }
}
