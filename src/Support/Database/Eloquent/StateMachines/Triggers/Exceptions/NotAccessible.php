<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Exceptions;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Stringable;
use RuntimeException;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachine;

class NotAccessible extends RuntimeException
{
    private Stringable $template { get => str('[%s] trigger is not accessible on [%s] outside the context of a [%s]'); }

    public function __construct(string $trigger, StateMachine&BackedEnum $enum)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [$trigger, $enum->name, class_basename(Model::class)])->toString()
        );
    }
}
