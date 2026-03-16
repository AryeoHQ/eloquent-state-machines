<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Console\Concerns;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;
use Tooling\GeneratorCommands\References\GenericClass;

use function Laravel\Prompts\search;

/**
 * @mixin \Illuminate\Console\GeneratorCommand
 * @mixin \Tooling\GeneratorCommands\Concerns\SearchesClasses
 */
trait RetrievesModel
{
    use RetrievesModelFromOption;

    public protected(set) GenericClass $model;

    /**
     * @param  Collection<int, string>  $classes
     * @return Collection<int, string>
     */
    protected function filterSearchableClasses(Collection $classes): Collection
    {
        return $classes->filter(fn (string $class) => $this->isSearchableModel($class));
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

    protected function isSearchableModel(string $class): bool
    {
        return rescue(fn () => is_a($class, EloquentModel::class, true) && ! (new \ReflectionClass($class))->isAbstract(), false, false);
    }
}
