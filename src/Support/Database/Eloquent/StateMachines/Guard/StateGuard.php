<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Guard;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Triggers\Exceptions\StateChangedOutsideTrigger;
use WeakMap;

final class StateGuard
{
    /** @var null|WeakMap<Model, array<string, int>> */
    private static null|WeakMap $running = null;

    private static int $lifted = 0;

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function withoutStateGuard(Closure $callback): mixed
    {
        self::$lifted++;

        try {
            return $callback();
        } finally {
            self::$lifted--;
        }
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function running(Model $model, string $field, Closure $callback): mixed
    {
        $running = self::$running ??= new WeakMap;

        $running[$model] = [...$running[$model] ?? [], $field => ($running[$model][$field] ?? 0) + 1];

        try {
            return $callback();
        } finally {
            $running[$model] = array_filter([...$running[$model], $field => $running[$model][$field] - 1], fn (int $count): bool => $count > 0);
        }
    }

    public static function updating(Model $model): void
    {
        if (self::$lifted > 0) {
            return;
        }

        $fields = collect($model->getCasts())
            ->filter(fn ($cast): bool => is_a($cast, StateMachineable::class, true))
            ->keys();

        if ($fields->isEmpty()) {
            return;
        }

        $dirty = $model->getDirty();

        $fields
            ->filter(fn (string $field): bool => array_key_exists($field, $dirty))
            ->reject(fn (string $field): bool => self::backing($dirty[$field]) === self::backing($model->getRawOriginal($field)))
            ->reject(fn (string $field): bool => (self::$running[$model][$field] ?? 0) > 0)
            ->whenNotEmpty(fn ($fields) => throw new StateChangedOutsideTrigger($model, $fields->first()));
    }

    private static function backing(mixed $value): null|string
    {
        return match (true) {
            is_null($value) => null,
            $value instanceof BackedEnum => (string) $value->value,
            default => (string) $value,
        };
    }
}
