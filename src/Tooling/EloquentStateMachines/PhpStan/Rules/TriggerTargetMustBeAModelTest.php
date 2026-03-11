<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;

/**
 * @extends RuleTestCase<TriggerTargetMustBeAModel>
 */
class TriggerTargetMustBeAModelTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new TriggerTargetMustBeAModel;
    }

    #[Test]
    public function it_passes_when_target_is_a_model(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/ValidTrigger.php')], []);
    }

    #[Test]
    public function it_fails_when_target_is_not_a_model(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/TargetNotModel.php')], [
            [
                'Property with #[Target] attribute must be a [Model], [int] given.',
                13,
            ],
        ]);
    }
}
