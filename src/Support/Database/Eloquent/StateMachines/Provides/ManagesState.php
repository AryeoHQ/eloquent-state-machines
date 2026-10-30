<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Provides;

use Illuminate\Support\Collection;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionEnumBackedCase;
use Support\Database\Eloquent\Casts\AsStateMachine;
use Support\Database\Eloquent\StateMachines\Attributes\Events;
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

        $this->preventInaccessibleTrigger($proxy, $name);

        $this->preventInvalidDeclarations();

        $transitions = $this->transitions()->filter(
            fn (Transition $transition): bool => str(class_basename($transition->using))->camel()->is($name)
        );

        $this->preventUndeclaredTrigger($transitions, $name);

        $this->preventDuplicateTrigger($transitions, $name);

        return with(
            $transitions->first(),
            fn (Transition $transition): Trigger => $transition->using::make(...$arguments)->to($transition->to)->from($this)->raise($transition->events)->on($proxy->model, $proxy->field)
        );
    }

    private function preventInaccessibleTrigger(mixed $proxy, string $name): void
    {
        throw_unless(is_object($proxy) && is_a($proxy, Proxy::class), Triggers\Exceptions\NotAccessible::class, $name, $this);
    }

    private function preventInvalidDeclarations(): void
    {
        static $checked = [];

        if (array_key_exists(static::class, $checked)) {
            return;
        }

        $transitions = collect(static::cases())->mapWithKeys(fn (self $case): array => [$case->name => $case->transitions()]);

        $this->preventDuplicateEvents($transitions);

        $this->preventStationaryEvents($transitions);

        $checked[static::class] = true;
    }

    /**
     * @param  Collection<string, Collection<array-key, Transition>>  $transitions
     */
    private function preventDuplicateEvents(Collection $transitions): void
    {
        collect(static::cases())
            ->flatMap(fn (self $case): array => [
                ...collect((new ReflectionEnumBackedCase($case, $case->name))->getAttributes(Events\Events::class))->map(fn (ReflectionAttribute $attribute): Events\Events => $attribute->newInstance()),
                ...$transitions[$case->name]->pluck('events')->filter(),
            ])
            ->flatMap(fn (Events\Events $events): array => [$events->before, $events->after])
            ->map(fn (string $event): string => (new ReflectionClass($event))->getName())
            ->duplicates()
            ->each(fn (string $event) => throw new Events\Exceptions\Duplicate($event, static::class));
    }

    /**
     * @param  Collection<string, Collection<array-key, Transition>>  $transitions
     */
    private function preventStationaryEvents(Collection $transitions): void
    {
        collect(static::cases())
            ->each(fn (self $case) => $transitions[$case->name]
                ->filter(fn (Transition $transition): bool => $transition->to === $case && $transition->events !== null)
                ->each(fn (Transition $transition) => throw new Events\Exceptions\Stationary($transition->using, $case)));
    }

    /**
     * @param  Collection<array-key, Transition>  $transitions
     */
    private function preventUndeclaredTrigger(Collection $transitions, string $name): void
    {
        throw_unless($transitions->isNotEmpty(), Triggers\Exceptions\Invalid::class, $name, $this);
    }

    /**
     * @param  Collection<array-key, Transition>  $transitions
     */
    private function preventDuplicateTrigger(Collection $transitions, string $name): void
    {
        throw_unless($transitions->count() === 1, Triggers\Exceptions\Duplicate::class, $name, $this);
    }

    final public function jsonSerialize(): int|string
    {
        return $this->value;
    }
}
