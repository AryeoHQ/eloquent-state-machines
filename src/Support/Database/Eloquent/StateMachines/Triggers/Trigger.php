<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Exceptions\Invalid;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Throwable;

abstract class Trigger implements Contracts\Trigger
{
    final public readonly StateMachineable&BackedEnum $to;

    private Model $model {
        get => $this->{$this->target()};
        set => $this->{$this->target()} = $value;
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    final public static function make(mixed ...$arguments): static
    {
        if (method_exists(static::class, '__construct')) {
            $reflection = new ReflectionMethod(static::class, '__construct');
            $parameters = $reflection->getParameters();

            // Support a mix of positional and named parameters, and default values
            $arguments = collect($arguments)->mapWithKeys(function ($value, $key) use ($parameters) {
                return is_int($key) ? [$parameters[$key]->name => $value] : [$key => $value];
            })->all();
        }

        return resolve(static::class, (array) $arguments);
    }

    final public function to(StateMachineable&BackedEnum $to): self
    {
        $this->to = $to;

        return $this;
    }

    final public function on(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function allowed(): bool
    {
        return true;
    }

    final public function blocked(): bool
    {
        return ! $this->allowed();
    }

    final public function run(): Model
    {
        throw_unless($this->allowed(), Invalid::class, $this->model, $this->to);

        $this->dispatchEvent($this->to->events()->before);

        $this->process();

        $this->dispatchEvent($this->to->events()->after);

        return $this->model;
    }

    private function process(): void
    {
        throw_unless(method_exists($this, 'handle'), Exceptions\NotProcessable::class, $this);

        rescue(
            function () {
                app()->call([$this, 'handle']);
                $this->transition();
            },
            function (Throwable $throwable) {
                when(
                    method_exists($this, 'failed'),
                    fn () => call_user_func([$this, 'failed'], $throwable)
                );

                throw $throwable;
            }
        );
    }

    private function dispatchEvent(string $event): void
    {
        Event::dispatch(new $event($this->model));
    }

    private function target(): string
    {
        $properties = collect((new ReflectionClass($this))->getProperties())->filter(
            fn (ReflectionProperty $property): bool => (bool) $property->getAttributes(Target\Target::class)
        );

        throw_unless($properties->isNotEmpty(), Target\Exceptions\NotDefined::class, $this);
        throw_unless($properties->count() === 1, Target\Exceptions\MultipleDefined::class, $this);

        return with(
            $properties->first(),
            function (ReflectionProperty $property) {
                throw_unless($property->getType() instanceof ReflectionNamedType, Target\Exceptions\NotModel::class, $this);
                throw_unless(is_subclass_of($property->getType()->getName(), Model::class), Target\Exceptions\NotModel::class, $this);

                return $property->getName();
            }
        );
    }

    private function transition(): void
    {
        $name = collect($this->model->getCasts())->filter(
            fn ($cast): bool => $cast === $this->to::class
        )->keys()->first();

        $this->model->forceFill([(string) $name => $this->to])->save();
    }
}
