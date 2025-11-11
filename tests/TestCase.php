<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench;

abstract class TestCase extends Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            \Support\Database\Eloquent\StateMachines\Provider::class,
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
