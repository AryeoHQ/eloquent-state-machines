<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Provides;

use Support\Database\Eloquent\Casts\AsStateMachine;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\Proxy;
use Support\Database\Eloquent\StateMachines\Triggers;
use Support\Database\Eloquent\StateMachines\Triggers\Contracts\Trigger;

trait ManagesState
{
    use DefinesEvents;
    use DefinesTransitions;

    /**
     * @return AsStateMachine<static>
     */
    final public static function castUsing(array $arguments): AsStateMachine
    {
        return resolve(AsStateMachine::class, ['enumClass' => static::class]);
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     */
    final public function __call(string $name, array $arguments): Trigger
    {
        $proxy = data_get(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2), '1.object');

        throw_unless(is_object($proxy) && is_a($proxy, Proxy::class), Triggers\Exceptions\NotAccessible::class, $name, $this);

        $transitions = $this->transitions()->filter(
            fn (Transition $transition): bool => str(class_basename($transition->using))->camel()->is($name)
        );

        throw_unless($transitions->isNotEmpty(), Triggers\Exceptions\Invalid::class, $name, $this);

        throw_unless($transitions->count() === 1, Triggers\Exceptions\Duplicate::class, $name, $this);

        return with(
            $transitions->first(),
            fn (Transition $transition): Trigger => $transition->using::make(...$arguments)->to($transition->to)->on($proxy->model)
        );
    }
}
