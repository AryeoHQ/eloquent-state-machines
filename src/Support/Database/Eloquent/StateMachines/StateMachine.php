<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Stringable;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;

/**
 * @template TEnum of StateMachineable&BackedEnum
 *
 * @mixin TEnum
 *
 * @method array<TEnum> cases()
 */
class StateMachine implements Contracts\Proxy, Stringable
{
    final public readonly Model $model;

    /**
     * @var TEnum
     */
    final public readonly StateMachineable&BackedEnum $enum;

    final public function __construct(Model $model, StateMachineable&BackedEnum $enum)
    {
        $this->model = $model;
        $this->enum = $enum;
    }

    /**
     * @template T of StateMachineable&BackedEnum
     *
     * @param  T  $enum
     * @return static<T>
     */
    final public static function make(Model $model, StateMachineable&BackedEnum $enum): static
    {
        return resolve(static::class, ['model' => $model, 'enum' => $enum]);
    }

    final public function __call(string $name, array $arguments): mixed
    {
        return $this->enum->$name(...$arguments);
    }

    final public function __toString(): string
    {
        return (string) $this->enum->value;
    }

    final public function jsonSerialize(): int|string
    {
        return $this->enum->jsonSerialize();
    }
}
