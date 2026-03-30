<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Console\Concerns;

use Illuminate\Support\Stringable;
use Symfony\Component\Console\Input\InputOption;

/**
 * @mixin \Illuminate\Console\GeneratorCommand
 */
trait RetrievesStateMachineablesFromOption
{
    protected function modelFromOption(): null|Stringable
    {
        if (! $this->hasOption('model')) {
            return null;
        }

        $provided = str($this->option('model')); // @phpstan-ignore argument.type

        if ($provided->isEmpty()) {
            return null;
        }

        return $provided;
    }

    /** @return array<int, InputOption> */
    protected function getModelInputOptions(): array
    {
        return [
            new InputOption('model', null, InputOption::VALUE_REQUIRED, 'The model FQCN (e.g. App\\Models\\User).'),
        ];
    }
}
