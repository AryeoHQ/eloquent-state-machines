<?php

declare(strict_types=1);

namespace Tests\Support\Database\Eloquent\StateMachines\Provides;

use PHPUnit\Framework\Attributes\Test;
use ReflectionEnum;
use Support\Database\Eloquent\StateMachines\Triggers\Exceptions\NotAccessible;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\TestCase;
use ValueError;

class ManagesStateTest extends TestCase
{
    #[Test]
    public function it_throws_an_exception_when_accessing_trigger_outside_of_model_context(): void
    {
        $enum = Status::Registered;

        $this->expectException(NotAccessible::class);

        $enum->activate();
    }

    #[Test]
    public function it_continues_to_behave_as_a_backed_enum(): void
    {
        $this->assertSame('registered', Status::Registered->value);
        $this->assertSame('Registered', Status::Registered->name);

        $this->assertSame(Status::Registered, Status::from('registered'));
        $this->assertSame(Status::Registered, Status::tryFrom('registered'));

        $this->expectException(ValueError::class);
        Status::from('__missing');
        $this->assertNull(Status::tryFrom('__missing'));

        tap(new ReflectionEnum(Status::class), function (ReflectionEnum $enum): void {
            $actual = collect($enum->getCases())->map->getValue();
            $this->assertSame($actual->toArray(), Status::cases());
        });
    }
}
