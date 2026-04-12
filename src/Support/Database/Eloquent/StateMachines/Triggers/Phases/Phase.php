<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Phases;

enum Phase: string
{
    case Before = 'before';

    case After = 'after';
}
