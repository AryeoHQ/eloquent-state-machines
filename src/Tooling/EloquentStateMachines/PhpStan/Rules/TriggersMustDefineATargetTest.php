<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;

/**
 * @extends RuleTestCase<TriggersMustDefineATarget>
 */
class TriggersMustDefineATargetTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new TriggersMustDefineATarget;
    }

    #[Test]
    public function it_passes_when_trigger_defines_a_target(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/ValidTrigger.php')], []);
    }

    #[Test]
    public function it_fails_when_trigger_does_not_define_a_target(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/MissingTarget.php')], [
            [
                'Triggers must define a property with the #[Target] attribute.',
                10,
            ],
        ]);
    }
}
