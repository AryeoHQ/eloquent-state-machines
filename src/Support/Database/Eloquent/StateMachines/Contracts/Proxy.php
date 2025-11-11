<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Contracts;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;

interface Proxy extends StateMachine
{
    public Model $model { get; }

    public static function make(Model $model, StateMachineable&BackedEnum $enum): static;
}
