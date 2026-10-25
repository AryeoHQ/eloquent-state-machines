<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use Support\Actions\Concerns\AsAction;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Exceptions\Invalid;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Guard\StateGuard;
use Support\Database\Eloquent\StateMachines\Triggers\Middleware\ThroughLifecycle;
use Support\Database\Eloquent\StateMachines\Triggers\Phases\Phase;
use Support\Database\Eloquent\StateMachines\Triggers\Phases\TransitionDuring;

#[TransitionDuring(Phase::After)]
abstract class Trigger implements Contracts\Trigger // @phpstan-ignore Action.final.required, Action.handle.required
{
    use AsAction {
        now as private actionNow;
    }

    final public protected(set) readonly StateMachineable&BackedEnum $to;

    final public protected(set) readonly StateMachineable&BackedEnum $from;

    private TransitionDuring $transitionDuring {
        get => $this->transitionDuring ??= collect([static::class, ...class_parents($this)])
            ->flatMap(fn (string $class) => (new ReflectionClass($class))->getAttributes(TransitionDuring::class))
            ->first()
            ->newInstance();
    }

    private bool $withoutTransaction {
        get => $this->withoutTransaction ??= collect([static::class, ...class_parents($this)])
            ->flatMap(fn (string $class) => (new ReflectionClass($class))->getAttributes(WithoutTransaction::class))
            ->isNotEmpty();
    }

    private Model $model {
        get => $this->{$this->target()};
        set => $this->{$this->target()} = $value;
    }

    private string $field {
        get => $this->field ??= collect($this->model->getCasts())->filter(
            fn ($cast): bool => $cast === $this->to::class
        )->keys()->first();
    }

    private null|(StateMachineable&BackedEnum) $current {
        get => self::state($this->to::class, $this->model->getRawOriginal($this->field));
    }

    private bool $changesState {
        get => $this->changesState ??= $this->current !== $this->to;
    }

    private null|(StateMachineable&BackedEnum) $source {
        get => $this->replaying ? $this->from : self::state($this->to::class, $this->model->getAttributes()[$this->field] ?? null);
    }

    private bool $replaying = false;

    /** @var array<string, mixed> */
    private array $snapshot = [];

    /** @var array<string, mixed> */
    private array $pending = [];

    private bool $transactional {
        get => ! $this->withoutTransaction && ! $this->replaying;
    }

    final public function prepare(): void
    {
        $this->through([...$this->middleware, ThroughLifecycle::class]);
    }

    final public function now(): Model
    {
        $this->actionNow();

        return $this->model;
    }

    /**
     * @param  array<int|string, mixed>  $arguments
     * @return array<string, mixed>
     */
    final public static function replay(Model $model, string $field, array $arguments = []): array
    {
        $cast = $model->getCasts()[$field] ?? null;

        throw_unless(
            is_string($cast) && is_a($cast, StateMachineable::class, true) && is_a($cast, BackedEnum::class, true),
            InvalidArgumentException::class,
            "[{$field}] on [".class_basename($model::class).'] is not a state machine.'
        );

        $to = self::state($cast, $model->getAttributes()[$field] ?? null);
        $from = self::state($cast, $model->getRawOriginal($field));

        throw_if(is_null($to), InvalidArgumentException::class, "[{$field}] on [".class_basename($model::class).'] holds no state to replay.');

        throw_if(is_null($from), InvalidArgumentException::class, "[{$field}] on [".class_basename($model::class).'] holds no source state to replay.');

        $transitions = $from->transitions()->filter(fn ($transition): bool => $transition->to === $to);

        throw_unless($transitions->isNotEmpty(), Invalid::class, $model, $to, $from);

        throw_unless($transitions->count() === 1, Exceptions\Duplicate::class, $to->name, $from);

        self::ensureDeclared($transitions->first()->using, $arguments);

        return with(
            $transitions->first()->using::make(...$arguments)->to($to)->from($from)->on($model),
            function (self $trigger) use ($model, $field): array {
                $trigger->field = $field;
                $trigger->replaying = true;
                $trigger->snapshot = $model->getAttributes();

                $trigger->actionNow();

                return $trigger->pending;
            }
        );
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

    final public function from(StateMachineable&BackedEnum $from): self
    {
        $this->from = $from;

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
        $lifecycle = function () use ($action) {
            $this->before();
            $result = $action();
            $this->after();

            return $result;
        };

        return StateGuard::running($this->model, $this->field, fn () => rescue(
            fn () => $this->transactional ? $this->model->getConnection()->transaction($lifecycle) : $lifecycle(),
            rescue: function (\Throwable $exception) {
                when($this->transactional, fn () => rescue(fn () => $this->model->refresh(), report: false));

                throw $exception;
            },
            report: false
        ));
    }

    final protected function before(): void
    {
        $this->preventInvalidTransition();

        $this->preventBlockedTransition();

        when($this->changesState, function () {
            $this->dispatchEvent($this->to->events()->before);
            $this->transition(Phase::Before);
        });
    }

    final protected function after(): void
    {
        when(! $this->failedOrReleased, function () {
            when($this->changesState, fn () => $this->transition(Phase::After));

            if ($this->replaying) {
                $this->pending = collect($this->model->getAttributes())->except($this->field)->reject(
                    fn ($value, string $key): bool => array_key_exists($key, $this->snapshot) && $this->snapshot[$key] === $value
                )->all();
            } else {
                $this->model->save();
            }

            when($this->changesState, fn () => $this->model->getConnection()->afterCommit(fn () => $this->dispatchEvent($this->to->events()->after)));
        });
    }

    private function preventInvalidTransition(): void
    {
        throw_unless(
            $this->current === $this->from,
            Invalid::class,
            $this->model,
            $this->to,
            $this->source
        );

        throw_unless(
            $this->replaying || $this->persisted() === $this->from,
            Invalid::class,
            $this->model,
            $this->to,
            $this->source
        );
    }

    private function persisted(): null|(StateMachineable&BackedEnum)
    {
        if (! $this->model->exists) {
            return $this->current;
        }

        return with(
            $this->model->getConnection()
                ->table($this->model->getTable())
                ->where($this->model->getKeyName(), $this->model->getOriginal($this->model->getKeyName()) ?? $this->model->getKey())
                ->lockForUpdate()
                ->value($this->field),
            fn ($value) => is_null($value) ? null : $this->to::tryFrom($value)
        );
    }

    private function preventBlockedTransition(): void
    {
        throw_if($this->blocked(), Invalid::class, $this->model, $this->to, $this->source);
    }

    private function dispatchEvent(string $event): void
    {
        $modelParameter = collect((new ReflectionClass($event))->getConstructor()?->getParameters() ?? [])->first();

        $event = resolve($event, $modelParameter ? [$modelParameter->getName() => $this->model] : []);

        event($event);
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
        if ($this->replaying || $this->transitionDuring->phase !== $phase) {
            return;
        }

        $this->model->forceFill([$this->field => $this->to])->save();
    }

    /**
     * @param  class-string<self>  $trigger
     * @param  array<int|string, mixed>  $arguments
     */
    private static function ensureDeclared(string $trigger, array $arguments): void
    {
        $declared = collect(method_exists($trigger, '__construct') ? (new ReflectionMethod($trigger, '__construct'))->getParameters() : [])
            ->map(fn (ReflectionParameter $parameter): string => strtolower($parameter->name));

        foreach (array_keys($arguments) as $name) {
            throw_if(
                is_int($name),
                InvalidArgumentException::class,
                'Replay arguments for ['.class_basename($trigger).'] must be named.'
            );

            throw_unless(
                $declared->contains(strtolower($name)),
                InvalidArgumentException::class,
                "[{$name}] is not an argument of [".class_basename($trigger).'].'
            );
        }
    }

    /**
     * @param  class-string<StateMachineable&BackedEnum>  $enum
     */
    private static function state(string $enum, mixed $value): null|(StateMachineable&BackedEnum)
    {
        return match (true) {
            $value instanceof $enum => $value,
            is_null($value) => null,
            default => $enum::tryFrom($value instanceof BackedEnum ? $value->value : $value),
        };
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
