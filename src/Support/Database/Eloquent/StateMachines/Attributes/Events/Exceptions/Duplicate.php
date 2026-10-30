<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions;

use Illuminate\Support\Stringable;
use LogicException;

class Duplicate extends LogicException
{
    private Stringable $template { get => str('Multiple [%s] events defined on [%s]'); }

    public function __construct(string $event, string $enum)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [$event, class_basename($enum)])->toString()
        );
    }
}
