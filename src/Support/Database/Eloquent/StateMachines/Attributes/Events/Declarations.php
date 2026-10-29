<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Attributes\Events;

use BackedEnum;
use Illuminate\Support\Collection;
use ReflectionAttribute;
use ReflectionEnumBackedCase;
use Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions\Reused;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;

final class Declarations
{
    /**
     * @param  class-string<StateMachineable&BackedEnum>  $enum
     */
    public static function preventReuse(string $enum): void
    {
        collect($enum::cases())
            ->flatMap(fn (StateMachineable&BackedEnum $case): Collection => self::of($case))
            ->groupBy('event')
            ->map(fn (Collection $declarations): Collection => $declarations->pluck('on')->unique()->values())
            ->filter(fn (Collection $on): bool => $on->count() > 1)
            ->each(fn (Collection $on, string $event) => throw new Reused($event, $on[0], $on[1]));
    }

    /**
     * @return Collection<int, array{event: string, on: string}>
     */
    private static function of(StateMachineable&BackedEnum $case): Collection
    {
        $reflection = new ReflectionEnumBackedCase($case, $case->name);

        return collect($reflection->getAttributes(Events::class))
            ->map(fn (ReflectionAttribute $attribute): array => ['on' => $case->name, 'events' => $attribute->newInstance()])
            ->merge(
                collect($reflection->getAttributes(Transition::class))
                    ->map(fn (ReflectionAttribute $attribute): Transition => $attribute->newInstance())
                    ->filter(fn (Transition $transition): bool => $transition->events !== null)
                    ->map(fn (Transition $transition): array => [
                        'on' => str(class_basename($transition->using))->camel()->prepend($case->name, '->')->append('()')->toString(),
                        'events' => $transition->events,
                    ])
            )
            ->flatMap(fn (array $declaration): array => [
                ['event' => $declaration['events']->before, 'on' => $declaration['on']],
                ['event' => $declaration['events']->after, 'on' => $declaration['on']],
            ]);
    }
}
