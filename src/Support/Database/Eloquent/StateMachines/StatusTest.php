<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines;

use Support\Database\Eloquent\StateMachines\Provides\DefinesEventsTestCases;
use Support\Database\Eloquent\StateMachines\Provides\DefinesTransitionsTestCases;
use Tests\TestCase;

class StatusTest extends TestCase
{
    use DefinesEventsTestCases;
    use DefinesTransitionsTestCases;
}
