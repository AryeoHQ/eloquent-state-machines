<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\Casts;

use PHPUnit\Framework\Attributes\Test;
use Support\Database\Eloquent\StateMachines\Contracts;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Support\Users\User;
use Tests\TestCase;

class AsStateMachineTest extends TestCase
{
    #[Test]
    public function it_casts_model_property_to_state_machine(): void
    {
        $user = User::factory()->registered()->make();

        $this->assertInstanceOf(Contracts\Proxy::class, $user->status);
    }

    #[Test]
    public function it_handles_get(): void
    {
        $this->assertInstanceOf(
            Contracts\Proxy::class,
            User::factory()->state(['status' => Status::Registered->value])->make()->status
        );

        $this->assertInstanceOf(
            Contracts\Proxy::class,
            User::factory()->state(['status' => Status::Registered])->make()->status
        );
    }

    #[Test]
    public function it_handles_set(): void
    {
        $user = User::factory()->make();
        $this->assertInstanceOf(
            Contracts\Proxy::class,
            $user->forceFill(['status' => 'registered'])->status
        );

        $this->assertInstanceOf(
            Contracts\Proxy::class,
            $user->forceFill(['status' => Status::Registered])->status
        );
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $user = User::factory()->registered()->make();

        $this->assertSame(
            $user->toJson(),
            json_encode($user)
        );
    }
}
