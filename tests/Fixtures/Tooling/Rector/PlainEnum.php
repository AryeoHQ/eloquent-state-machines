<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\Rector;

enum PlainEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
