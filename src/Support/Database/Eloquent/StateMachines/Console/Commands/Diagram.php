<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Stringable;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;
use Support\Database\Eloquent\StateMachines\Diagrams;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use Tooling\Composer\Composer;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\suggest;

#[AsCommand(name: 'state-machine:diagram', description: 'Generate a Mermaid state diagram for a StateMachineable enum')]
class Diagram extends Command implements PromptsForMissingInput
{
    private Composer $composer {
        get => $this->composer ??= new Composer;
    }

    /** @var Collection<array-key, Stringable> */
    private Collection $stateMachineables {
        get => $this->stateMachineables ??= $this->composer->classMap
            ->reject(fn (string $path) => str_contains($path, '/vendor/'))
            ->filter(fn (string $path, string $class) => rescue(fn () => is_subclass_of($class, StateMachineable::class), false, false))
            ->map(
                fn (string $path, string $class) => str(View::make('state-machine::diagram', [
                    'direction' => $this->option('direction'),
                    'stateMachineable' => $class,
                ])->render())->rtrim(PHP_EOL)
            );
    }

    /** @var Collection<array-key, Stringable> */
    private Collection $potentialFiles {
        get => $this->potentialFiles ??= collect(
            Finder::create()
                ->in($this->composer->baseDirectory->toString())
                ->files()
                ->name('*.md')
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->ignoreVCSIgnored(true)
        )->map(
            fn ($file) => str($file->getRelativePathname())
        );
    }

    public function handle(): int
    {
        Process::run('composer dump-autoload -o');

        return when(
            $this->option('update'),
            fn () => $this->handleUpdate(),
            fn () => $this->handleDisplay()
        );
    }

    private function handleUpdate(): int
    {
        progress(
            label: 'Updating diagrams in files...',
            steps: $this->potentialFiles->count(),
            callback: fn () => $this->potentialFiles->each(function (Stringable $path) {
                $found = Diagrams\Diagram::extractFrom($path->toString());
                $found->each(function (Diagrams\Diagram $diagram) use ($path) {
                    if (! $diagram->isMermaidable && ! $this->promptForDiagramDecision($diagram, $path)) {
                        return;
                    }

                    $diagram->updateIn($path->toString());
                });
            })
        );

        return self::SUCCESS;
    }

    private function handleDisplay(): int
    {
        $this->stateMachineables->each(function (Stringable $diagram, string $class) {
            $this->info($diagram->toString());
        });

        return self::SUCCESS;
    }

    private function promptForDiagramDecision(Diagrams\Diagram $diagram, Stringable $path): bool
    {
        $this->warn("The state machine [{$diagram->current}] found in [{$path->toString()}] no longer exists");

        $decision = select(
            label: 'What would you like to do?',
            options: [
                'replace' => 'Use a different state machine',
                'remove' => 'Remove the diagram from the file',
                'skip' => 'Skip for now',
            ],
        );

        return match ($decision) {
            'replace' => (bool) $diagram->convertTo(
                suggest(
                    label: 'Available state machines...',
                    options: fn ($value) => $this->stateMachineables->keys()->filter(
                        fn (string $class) => str($class)->lower()->is('*'.strtolower($value).'*')
                    )->toArray()
                )
            ),
            'remove' => (bool) $diagram->markForRemoval(),
            default => false,
        };
    }

    protected function getOptions(): array
    {
        return [
            ['direction', 'd', InputOption::VALUE_REQUIRED, 'The diagram direction', null],
            ['update', 'u', InputOption::VALUE_NONE, 'Only update existing diagrams in files', null],
        ];
    }
}
