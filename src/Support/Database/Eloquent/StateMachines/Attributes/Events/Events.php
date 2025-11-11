<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Attributes\Events;

use Attribute;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions\NotFound;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Events
{
    public string $before;

    public string $after;

    public function __construct(string $before, string $after)
    {
        throw_unless(class_exists($before), NotFound::class, 'before', $before);
        throw_unless(class_exists($after), NotFound::class, 'after', $after);

        $this->before = $before;
        $this->after = $after;
    }
}
