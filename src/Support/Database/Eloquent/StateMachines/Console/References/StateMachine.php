<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Console\References;

use Illuminate\Support\Stringable;
use Tooling\GeneratorCommands\References\Contracts\Reference;
use Tooling\GeneratorCommands\References\GenericClass;
use Tooling\GeneratorCommands\References\TestClass;

final class StateMachine implements Reference
{
    public GenericClass $model;

    public string $stateMachine;

    public function __construct(GenericClass $model, string $stateMachine)
    {
        $this->model = $model;
        $this->stateMachine = $stateMachine;
    }

    public Stringable $stubPath {
        get => str(__DIR__.'/stubs/state-machine.stub');
    }

    public Stringable $name {
        get => str($this->stateMachine)->studly();
    }

    public Stringable $baseNamespace {
        get => $this->model->baseNamespace;
    }

    public null|Stringable $subNamespace {
        get => null;
    }

    public Stringable $subdirectory {
        get => $this->name;
    }

    public Stringable $namespace {
        get => $this->model->namespace->append('\\', $this->subdirectory->toString());
    }

    public Stringable $fqcn {
        get => $this->namespace->append('\\', $this->name->toString());
    }

    public Stringable $directory {
        get => $this->model->directory->append('/', $this->subdirectory->toString());
    }

    public Stringable $filePath {
        get => $this->directory->append('/', $this->name->toString(), '.php');
    }

    public TestClass $test {
        get => resolve(TestClass::class, [
            'name' => $this->name->append('Test'),
            'baseNamespace' => $this->namespace,
        ]);
    }
}
