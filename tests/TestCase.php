<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
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

    protected function defineDatabaseMigrations()
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('status')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('deactivated_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
