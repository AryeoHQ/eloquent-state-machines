<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Extensions;

use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\EloquentStateMachines\NotATrigger;
use Tests\Fixtures\Tooling\EloquentStateMachines\ValidTrigger;

class TargetPropertyIsAlwaysUsedExtensionTest extends PHPStanTestCase
{
    private TargetPropertyIsAlwaysUsedExtension $extension;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = new TargetPropertyIsAlwaysUsedExtension;
    }

    #[Test]
    public function it_marks_target_property_on_trigger_as_always_read(): void
    {
        $property = $this->resolveProperty(ValidTrigger::class, 'user');

        $this->assertTrue($this->extension->isAlwaysRead($property, 'user'));
    }

    #[Test]
    public function it_marks_target_property_on_trigger_as_always_written(): void
    {
        $property = $this->resolveProperty(ValidTrigger::class, 'user');

        $this->assertTrue($this->extension->isAlwaysWritten($property, 'user'));
    }

    #[Test]
    public function it_marks_target_property_on_trigger_as_initialized(): void
    {
        $property = $this->resolveProperty(ValidTrigger::class, 'user');

        $this->assertTrue($this->extension->isInitialized($property, 'user'));
    }

    #[Test]
    public function it_does_not_mark_target_property_on_non_trigger_class(): void
    {
        $property = $this->resolveProperty(NotATrigger::class, 'user');

        $this->assertFalse($this->extension->isAlwaysRead($property, 'user'));
        $this->assertFalse($this->extension->isAlwaysWritten($property, 'user'));
        $this->assertFalse($this->extension->isInitialized($property, 'user'));
    }

    private function resolveProperty(string $className, string $propertyName): \PHPStan\Reflection\ExtendedPropertyReflection
    {
        $reflectionProvider = $this->createReflectionProvider();

        return $reflectionProvider
            ->getClass($className)
            ->getNativeProperty($propertyName);
    }
}
