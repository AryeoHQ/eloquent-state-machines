<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Attributes\Transitions\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Stringable;
use RuntimeException;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;

class NotBound extends Invalid
{
    private Stringable $template { get => str('[%s] is not bound to a state column on [%s].'); }

    public function __construct(Trigger $trigger, Model $model) // @phpstan-ignore constructor.missingParentCall
    {
        RuntimeException::__construct(
            $this->template->replaceArray('%s', [$trigger::class, class_basename($model::class)])->toString()
        );
    }
}
