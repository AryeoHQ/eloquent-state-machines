<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\PhpStan\StateMachines;

use Support\Database\Eloquent\StateMachines\Provides\ManagesState;

enum ManagesStateOnly: string
{
    use ManagesState;

    case Active = 'active';
}
