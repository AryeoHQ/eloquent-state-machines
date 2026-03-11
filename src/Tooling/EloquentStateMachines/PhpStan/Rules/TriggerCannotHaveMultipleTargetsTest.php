<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;

/**
 * @extends RuleTestCase<TriggerCannotHaveMultipleTargets>
 */
class TriggerCannotHaveMultipleTargetsTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new TriggerCannotHaveMultipleTargets;
    }

    #[Test]
    public function it_passes_when_trigger_has_single_target(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/ValidTrigger.php')], []);
    }

    #[Test]
    public function it_fails_when_trigger_has_multiple_targets(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/MultipleTargets.php')], [
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
