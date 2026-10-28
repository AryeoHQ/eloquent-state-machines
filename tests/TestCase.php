<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench;
use Support\Database\Eloquent\StateMachines\Provider;

abstract class TestCase extends Testbench\TestCase
{
    protected $enablesPackageDiscoveries = true;

    protected function getPackageProviders($app): array
    {
        return [
            Provider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', $connection = env('DB_CONNECTION', 'sqlite'));
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.secondary', $app['config']->get("database.connections.{$connection}"));
    }

    protected function defineDatabaseMigrations()
    {
        collect([config('database.default'), 'secondary'])->each(function (string $connection): void {
            Schema::connection($connection)->dropIfExists('users');

            Schema::connection($connection)->create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('status')->nullable();
                $table->string('legacy_status')->nullable();
                $table->string('previous_status')->nullable();
                $table->timestampTz('activated_at')->nullable();
                $table->timestampTz('deactivated_at')->nullable();
                $table->timestampTz('suspended_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        });

        Schema::dropIfExists('jobs');

        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    protected function destroyDatabaseMigrations()
    {
        collect([config('database.default'), 'secondary'])->each(fn (string $connection) => DB::disconnect($connection));
    }
}
