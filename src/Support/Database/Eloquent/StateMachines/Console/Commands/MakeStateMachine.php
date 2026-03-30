<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Stringable;
use Support\Database\Eloquent\StateMachines\Console\Concerns\RetrievesStateMachineables;
use Support\Database\Eloquent\StateMachines\Console\References\StateMachine;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Tooling\GeneratorCommands\Concerns\CreatesColocatedTests;
use Tooling\GeneratorCommands\Concerns\GeneratorCommandCompatibility;
use Tooling\GeneratorCommands\Contracts\GeneratesFile;

#[AsCommand(name: 'make:state-machine', description: 'Create a new state machine.')]
class MakeStateMachine extends GeneratorCommand implements GeneratesFile
{
    use CreatesColocatedTests;
    use GeneratorCommandCompatibility;
    use RetrievesStateMachineables;

    protected $type = 'State Machine';

    public string $stub = __DIR__.'/stubs/state-machine.stub';

    public Stringable $nameInput {
        get => str($this->argument('name'));
    }

    public StateMachine $reference {
        get => new StateMachine($this->model, $this->getNameInput());
    }

    public function handle()
    {
        $this->resolveModel();

        return parent::handle();
    }

    /** @return array<int, InputArgument> */
    protected function getArguments(): array
    {
        return [
            new InputArgument('name', InputArgument::REQUIRED, 'The name of the state machine'),
        ];
    }

    /** @return array<int, InputOption> */
    protected function getOptions(): array
    {
        return [
            ...$this->getModelInputOptions(),
            new InputOption('force', 'f', InputOption::VALUE_NONE, 'Create the class even if the state machine already exists'),
        ];
    }
}
