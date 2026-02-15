<?php

declare(strict_types=1);

namespace Tests\Tooling\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Tooling\Concerns\GetsFixtures;
use Tooling\EloquentStateMachines\PhpStan\Rules\TriggerCannotHaveMultipleTargets;

/**
 * @extends RuleTestCase<TriggerCannotHaveMultipleTargets>
 */
class TriggerCannotHaveMultipleTargetsTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new TriggerCannotHaveMultipleTargets(
            self::getContainer()->getByType(\PHPStan\Reflection\ReflectionProvider::class)
        );
    }

    #[Test]
    public function it_passes_when_trigger_has_single_target(): void
    {
        $this->analyse([$this->getFixturePath('PhpStan/Triggers/ValidTrigger.php')], []);
    }

    #[Test]
    public function it_fails_when_trigger_has_multiple_targets(): void
    {
        $this->analyse([$this->getFixturePath('PhpStan/Triggers/MultipleTargets.php')], [
            [
                'Only one property with #[Target] attribute permitted.',
                14,
            ],
            [
                'Only one property with #[Target] attribute permitted.',
                17,
            ],
        ]);
    }
}
