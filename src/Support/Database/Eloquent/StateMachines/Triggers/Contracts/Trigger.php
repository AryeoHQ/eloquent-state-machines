<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers\Contracts;

use Illuminate\Database\Eloquent\Model;

interface Trigger
{
    public function run(): Model;

    public function allowed(): bool;

    public function blocked(): bool;
}
