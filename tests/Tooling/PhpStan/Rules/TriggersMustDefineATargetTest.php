<?php

declare(strict_types=1);

namespace Tests\Tooling\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Tooling\Concerns\GetsFixtures;
use Tooling\EloquentStateMachines\PhpStan\Rules\TriggersMustDefineATarget;

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
        $this->analyse([$this->getFixturePath('PhpStan/Triggers/ValidTrigger.php')], []);
    }

    #[Test]
    public function it_fails_when_trigger_does_not_define_a_target(): void
    {
        $this->analyse([$this->getFixturePath('PhpStan/Triggers/MissingTarget.php')], [
            [
                'Triggers must define a property with the #[Target] attribute.',
                10,
            ],
        ]);
    }
}
