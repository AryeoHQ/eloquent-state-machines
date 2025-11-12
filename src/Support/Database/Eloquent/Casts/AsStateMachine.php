<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Support\Database\Eloquent\StateMachines;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;

/**
 * @template TEnum of StateMachineable&BackedEnum
 */
class AsStateMachine implements CastsAttributes
{
    /** @var class-string<TEnum> */
    final public readonly string $enumClass;

    public bool $withoutObjectCaching = true;

    /**
     * @param  class-string<TEnum>  $enumClass
     */
    public function __construct(string $enumClass)
    {
        $this->enumClass = $enumClass;
    }

    /**
     * @return StateMachines\StateMachine<TEnum>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): StateMachines\StateMachine
    {
        return StateMachines\StateMachine::make($model, $this->resolveEnum($value));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string|int
    {
        return $this->resolveEnum($value)->value;
    }

    /**
     * @return TEnum
     */
    private function resolveEnum(mixed $value): StateMachineable&BackedEnum
    {
        $enum = match ($value instanceof $this->enumClass) {
            true => $value,
            false => $this->enumClass::from($value),
        };

        /** @var TEnum $enum */
        return $enum;
    }
}
