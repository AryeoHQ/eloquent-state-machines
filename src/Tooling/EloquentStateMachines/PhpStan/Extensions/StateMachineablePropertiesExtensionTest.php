<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Extensions;

use Illuminate\Database\Eloquent\Model;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\ObjectType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Tooling\EloquentStateMachines\PlainEnum;
use Tooling\EloquentStateMachines\PhpStan\Rules\StateMachineableMustUseManagesState;

/**
 * @extends RuleTestCase<StateMachineableMustUseManagesState>
 */
#[CoversClass(StateMachineablePropertiesExtension::class)]
class StateMachineablePropertiesExtensionTest extends RuleTestCase
{
    private StateMachineablePropertiesExtension $extension;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = new StateMachineablePropertiesExtension;
    }

    protected function getRule(): Rule
    {
        return new StateMachineableMustUseManagesState;
    }

    private function getClassReflection(string $class): ClassReflection
    {
        return $this->createReflectionProvider()->getClass($class);
    }

    #[Test]
    public function it_provides_enum_property_for_state_machineable_enums(): void
    {
        $reflection = $this->getClassReflection(Status::class);

        $this->assertTrue($this->extension->hasProperty($reflection, 'enum'));
    }

    #[Test]
    public function it_provides_model_property_for_state_machineable_enums(): void
    {
        $reflection = $this->getClassReflection(Status::class);

        $this->assertTrue($this->extension->hasProperty($reflection, 'model'));
    }

    #[Test]
    public function it_does_not_provide_unknown_properties(): void
    {
        $reflection = $this->getClassReflection(Status::class);

        $this->assertFalse($this->extension->hasProperty($reflection, 'foo'));
    }

    #[Test]
    public function it_does_not_provide_properties_for_non_state_machineable_enums(): void
    {
        $reflection = $this->getClassReflection(PlainEnum::class);

        $this->assertFalse($this->extension->hasProperty($reflection, 'enum'));
    }

    #[Test]
    public function it_does_not_provide_properties_for_non_enum_classes(): void
    {
        $reflection = $this->getClassReflection(Model::class);

        $this->assertFalse($this->extension->hasProperty($reflection, 'enum'));
    }

    #[Test]
    public function enum_property_returns_self_type(): void
    {
        $reflection = $this->getClassReflection(Status::class);

        $property = $this->extension->getProperty($reflection, 'enum');

        $this->assertInstanceOf(ObjectType::class, $property->getReadableType());
        $this->assertSame(Status::class, $property->getReadableType()->getClassName());
    }

    #[Test]
    public function model_property_returns_model_type(): void
    {
        $reflection = $this->getClassReflection(Status::class);

        $property = $this->extension->getProperty($reflection, 'model');

        $this->assertInstanceOf(ObjectType::class, $property->getReadableType());
        $this->assertSame(Model::class, $property->getReadableType()->getClassName());
    }

    #[Test]
    public function properties_are_public_and_readonly(): void
    {
        $reflection = $this->getClassReflection(Status::class);

        $property = $this->extension->getProperty($reflection, 'enum');

        $this->assertTrue($property->isPublic());
        $this->assertTrue($property->isReadable());
        $this->assertFalse($property->isWritable());
        $this->assertFalse($property->isStatic());
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [dirname(__DIR__, 5).'/tooling/phpstan/phpstan.neon'];
    }
}
