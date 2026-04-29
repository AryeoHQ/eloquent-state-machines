<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use Support\Actions\Concerns\AsAction;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Exceptions\Invalid;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Triggers\Middleware\ThroughLifecycle;
use Support\Database\Eloquent\StateMachines\Triggers\Phases\Phase;
use Support\Database\Eloquent\StateMachines\Triggers\Phases\TransitionDuring;

#[TransitionDuring(Phase::After)]
abstract class Trigger implements Contracts\Trigger // @phpstan-ignore Action.final.required, Action.handle.required
{
    use AsAction {
        now as private actionNow;
    }

    final public readonly StateMachineable&BackedEnum $to;

    private TransitionDuring $transitionDuring {
        get => $this->transitionDuring ??= collect([static::class, ...class_parents($this)])
            ->flatMap(fn (string $class) => (new ReflectionClass($class))->getAttributes(TransitionDuring::class))
            ->first()
            ->newInstance();
    }

    private Model $model {
        get => $this->{$this->target()};
        set => $this->{$this->target()} = $value;
    }

    final public function prepare(): void
    {
        $this->through([ThroughLifecycle::class, ...$this->middleware]);
    }

    final public function now(): Model
    {
        $this->actionNow();

        return $this->model;
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    final public static function make(mixed ...$arguments): static
    {
        if (method_exists(static::class, '__construct')) {
            $reflection = new ReflectionMethod(static::class, '__construct');
            $arguments = static::normalizeArguments($arguments, $reflection->getParameters());
        }

        return resolve(static::class, $arguments);
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

    /**
     * NOTE: You may be tempted to just push the `DB::transaction()` into the
     * overridden `now()`. While that would work when the `Trigger` is
     * executed `->now()`, if it was executed with `->dispatch()`
     * the lifecycle flow would execute twice.
     *
     * @param  \Closure(): mixed  $action
     */
    final public function lifecycle(\Closure $action): mixed
    {
        return DB::transaction(function () use ($action) {
            $this->before();
            $result = $action();
            $this->after();

            return $result;
        });
    }

    final protected function before(): void
    {
        throw_unless($this->allowed(), Invalid::class, $this->model, $this->to);

        $this->dispatchEvent($this->to->events()->before);
        $this->transition(Phase::Before);
    }

    final protected function after(): void
    {
        $this->transition(Phase::After);
        $this->model->save();
        $this->dispatchEvent($this->to->events()->after);
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

    private function transition(Phase $phase): void
    {
        if ($this->transitionDuring->phase !== $phase) {
            return;
        }

        $name = collect($this->model->getCasts())->filter(
            fn ($cast): bool => $cast === $this->to::class
        )->keys()->first();

        $this->model->forceFill([(string) $name => $this->to])->save();
    }

    /**
     * Normalize constructor arguments to support positional, named, and default parameters.
     *
     * @param  array<int|string, mixed>  $arguments
     * @param  array<ReflectionParameter>  $parameters
     * @return array<int|string, mixed>
     */
    protected static function normalizeArguments(array $arguments, array $parameters): array
    {
        $parameterCount = count($parameters);
        $lastParameter = $parameterCount > 0 ? $parameters[$parameterCount - 1] : null;

        // Build a case-insensitive map of parameter names for named argument lookup
        $parameterMap = collect($parameters)->mapWithKeys(
            fn ($param) => [strtolower($param->name) => $param->name]
        );

        return collect($arguments)->mapWithKeys(function ($value, $key) use ($parameters, $parameterCount, $lastParameter, $parameterMap) {
            if (is_int($key)) {
                // Positional argument - validate bounds
                if ($key >= $parameterCount) {
                    // Allow if last parameter is variadic
                    if ($lastParameter?->isVariadic()) {
                        return [$lastParameter->name => $value];
                    }

                    throw new InvalidArgumentException(
                        sprintf(
                            'Too many positional arguments for %s constructor. Expected at most %d, got at least %d.',
                            static::class,
                            $parameterCount,
                            $key + 1
                        )
                    );
                }

                return [$parameters[$key]->name => $value];
            }

            // Named argument - normalize case to match parameter name
            $normalizedKey = $parameterMap[strtolower($key)] ?? $key;

            return [$normalizedKey => $value];
        })->all();
    }
}
