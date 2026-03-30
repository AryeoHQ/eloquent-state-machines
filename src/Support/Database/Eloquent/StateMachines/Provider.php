<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines;

use Illuminate\Support\ServiceProvider;
use Support\Database\Eloquent\StateMachines\Console\Commands\Diagram;
use Support\Database\Eloquent\StateMachines\Console\Commands\MakeStateMachine;
use Tooling\EloquentStateMachines\Composer\ClassMap\Collectors\StateMachineables;
use Tooling\EloquentStateMachines\Composer\ClassMap\Collectors\StateMachines;

class Provider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([StateMachineables::class, StateMachines::class], 'tooling.classmap.collectors');
    }

    public function boot(): void
    {
        $this->bootViews();
        $this->bootCommands();
    }

    private function bootViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../../../../resources/views', 'state-machine');
        $this->loadViewsFrom(__DIR__.'/../../../../../resources/views/rector/rules', 'state-machines.rector.rules.samples');
    }

    private function bootCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Diagram::class,
            MakeStateMachine::class,
        ]);
    }
}
