<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Console\Commands;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Support\Database\Eloquent\StateMachines\Console\References\StateMachine;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Provides\ManagesState;
use Tests\TestCase;
use Tooling\Composer\Composer;
use Tooling\EloquentStateMachines\Composer\ClassMap\Collectors\StateMachineables;
use Tooling\GeneratorCommands\References\GenericClass;
use Tooling\GeneratorCommands\Testing\Concerns\GeneratesFileTestCases;

#[CoversClass(MakeStateMachine::class)]
class MakeStateMachineTest extends TestCase
{
    use GeneratesFileTestCases;

    public StateMachine $reference {
        get => new StateMachine(GenericClass::fromFqcn('App\\Models\\TestModel'), 'Workflow');
    }

    /** @var array<string, mixed> */
    public array $baselineInput {
        get => ['name' => 'Workflow', '--model' => 'App\\Models\\TestModel', '--no-test' => true];
    }

    #[Test]
    public function it_generates_a_state_machine_that_implements_state_machineable(): void
    {
        Composer::fake();

        $this->artisan($this->command, $this->baselineInput)->assertSuccessful();

        $contents = File::get($this->expectedFilePath);

        $this->assertStringContainsString('implements '.class_basename(StateMachineable::class), $contents);
    }

    #[Test]
    public function it_generates_a_state_machine_that_uses_manages_state(): void
    {
        Composer::fake();

        $this->artisan($this->command, $this->baselineInput)->assertSuccessful();

        $contents = File::get($this->expectedFilePath);

        $this->assertStringContainsString('use '.class_basename(ManagesState::class).';', $contents);
    }

    #[Test]
    public function it_generates_a_backed_enum(): void
    {
        Composer::fake();

        $this->artisan($this->command, $this->baselineInput)->assertSuccessful();

        $contents = File::get($this->expectedFilePath);

        $this->assertStringContainsString('enum Workflow: string', $contents);
    }

    #[Test]
    public function it_creates_a_colocated_test(): void
    {
        Composer::fake();

        $this->artisan($this->command, ['name' => 'Workflow', '--model' => 'App\\Models\\TestModel'])->assertSuccessful();

        $this->assertTrue(File::exists($this->reference->test->filePath->toString()));
    }

    #[Test]
    public function it_prompts_for_model_when_option_is_omitted(): void
    {
        Composer::fake();

        $target = tap(
            GenericClass::fromFqcn('App\\Models\\ModelPromptTarget'),
            fn (GenericClass $entity) => StateMachineables::fake([$entity->fqcn->ltrim('\\')->toString()])
        );

        $this->artisan($this->command, ['name' => 'Workflow', '--no-test' => true])
            ->expectsSearch('Which model?', $target->fqcn->ltrim('\\')->toString(), 'ModelPromptTarget', [$target->fqcn->ltrim('\\')->toString()])
            ->assertSuccessful();
    }

    #[Test]
    public function it_warns_and_prompts_when_model_is_not_fully_qualified(): void
    {
        Composer::fake();

        $target = tap(
            GenericClass::fromFqcn('App\\Models\\ModelPromptTarget'),
            fn (GenericClass $entity) => StateMachineables::fake([$entity->fqcn->ltrim('\\')->toString()])
        );

        $this->artisan($this->command, ['name' => 'Workflow', '--model' => 'ModelPromptTarget', '--no-test' => true])
            ->expectsOutputToContain('fully-qualified class name')
            ->expectsSearch('Which model?', $target->fqcn->ltrim('\\')->toString(), 'ModelPromptTarget', [$target->fqcn->ltrim('\\')->toString()])
            ->assertSuccessful();
    }
}
