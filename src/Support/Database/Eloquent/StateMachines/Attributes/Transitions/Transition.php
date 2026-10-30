<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Attributes\Transitions;

use Attribute;
use BackedEnum;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Events;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Triggers\Exceptions\NotFound;
use Support\Database\Eloquent\StateMachines\Triggers\Trigger;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Transition
{
    public StateMachineable&BackedEnum $to;

    /** @var class-string<Trigger> */
    public string $using;

    public null|Events $events;

    public function __construct(StateMachineable&BackedEnum $to, string $using, null|Events $events = null)
    {
        throw_unless(is_a($using, Trigger::class, true), NotFound::class, $using);

        $this->to = $to;
        $this->using = $using;
        $this->events = $events;
    }
}
