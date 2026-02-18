<?php

declare(strict_types=1);

namespace Tests\Tooling\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Tooling\Concerns\GetsFixtures;
use Tooling\EloquentStateMachines\PhpStan\Rules\TriggerTargetMustBeAModel;

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
        $this->analyse([$this->getFixturePath('PhpStan/Triggers/ValidTrigger.php')], []);
    }

    #[Test]
    public function it_fails_when_target_is_not_a_model(): void
    {
        $this->analyse([$this->getFixturePath('PhpStan/Triggers/TargetNotModel.php')], [
            [
                'Property with #[Target] attribute must be a [Model], [int] given.',
                13,
            ],
        ]);
    }
}
