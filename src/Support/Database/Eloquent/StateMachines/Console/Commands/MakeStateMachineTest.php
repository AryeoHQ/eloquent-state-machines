<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Console\Commands;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Support\Database\Eloquent\StateMachines\Console\References\StateMachine;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tests\TestCase;
use Tooling\GeneratorCommands\References\GenericClass;
use Tooling\GeneratorCommands\Testing\Concerns\CleansUpGeneratorCommands;
use Tooling\GeneratorCommands\Testing\Concerns\GeneratesFileTestCases;

#[CoversClass(MakeStateMachine::class)]
class MakeStateMachineTest extends TestCase
{
    use CleansUpGeneratorCommands;
    use GeneratesFileTestCases;

    /** @var array<array-key, string> */
    protected array $files {
        get => [
            $this->reference->directory->append('/*')->toString(),
            $this->reference->model->filePath->toString(),
        ];
    }

    public StateMachine $reference {
        get => new StateMachine(GenericClass::fromFqcn('Workbench\\App\\Models\\TestModel'), 'Workflow');
    }

    /** @var array<string, mixed> */
    public array $baselineInput {
        get => ['name' => 'Workflow', '--model' => 'Workbench\\App\\Models\\TestModel', '--no-test' => true];
    }

    #[Test]
    public function it_generates_a_state_machine_that_implements_state_machineable(): void
    {
        $this->artisan('make:model', ['name' => 'TestModel', '--force' => true]);
        $this->artisan($this->command, $this->baselineInput)->assertSuccessful();

        $contents = file_get_contents($this->expectedFilePath);

        $this->assertStringContainsString('implements '.class_basename(StateMachineable::class), $contents);
    }

    #[Test]
    public function it_generates_a_state_machine_that_uses_manages_state(): void
    {
        $this->artisan('make:model', ['name' => 'TestModel', '--force' => true]);
        $this->artisan($this->command, $this->baselineInput)->assertSuccessful();

        $contents = file_get_contents($this->expectedFilePath);

        $this->assertStringContainsString('use '.class_basename(ManagesState::class).';', $contents);
    }

    #[Test]
    public function it_generates_a_backed_enum(): void
    {
        $this->artisan('make:model', ['name' => 'TestModel', '--force' => true]);
        $this->artisan($this->command, $this->baselineInput)->assertSuccessful();

        $contents = file_get_contents($this->expectedFilePath);

        $this->assertStringContainsString('enum Workflow: string', $contents);
    }

    #[Test]
    public function it_creates_a_colocated_test(): void
    {
        $this->artisan('make:model', ['name' => 'TestModel', '--force' => true]);
        $this->artisan($this->command, ['name' => 'Workflow', '--model' => 'Workbench\\App\\Models\\TestModel'])->assertSuccessful();

        $this->assertFileExists($this->reference->test->filePath->toString());
    }
}
