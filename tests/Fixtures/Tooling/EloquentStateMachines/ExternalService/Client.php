<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tooling\EloquentStateMachines\ExternalService;

class Client
{
    public static function connect(): void {}

    public function send(int $id): void {}
}
