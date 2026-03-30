<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Console\Concerns;

use Illuminate\Support\Stringable;
use Tooling\Composer\ClassMap\Collectors\Contracts\Collector;
use Tooling\EloquentStateMachines\Composer\ClassMap\Collectors\StateMachineables;
use Tooling\GeneratorCommands\Concerns\SearchesAutoloadCaches;
use Tooling\GeneratorCommands\References\GenericClass;

use function Laravel\Prompts\search;

/**
 * @mixin \Illuminate\Console\GeneratorCommand
 */
trait RetrievesStateMachineables
{
    use RetrievesStateMachineablesFromOption;
    use SearchesAutoloadCaches;

    public protected(set) GenericClass $model;

    /** @return class-string<Collector> */
    protected function collector(): string
    {
        return StateMachineables::class;
    }

    public function resolveModel(): void
    {
        $this->model = GenericClass::fromFqcn($this->retrieveModel());
    }

    public function retrieveModel(): Stringable
    {
        $input = $this->modelFromOption();

        return $input !== null ? $this->qualifyModelName($input) : $this->modelFromPrompt();
    }

    protected function qualifyModelName(Stringable $name): Stringable
    {
        if ($name->contains('\\')) {
            return $name;
        }

        $this->components->warn('Please provide a fully-qualified class name (e.g. App\\Models\\User).');

        return $this->modelFromPrompt();
    }

    public function modelFromPrompt(): Stringable
    {
        return str(search(
            label: 'Which model?',
            options: fn ($search) => $this->getClassSearchResults($search),
            required: true,
            scroll: 5,
        ));
    }
}
