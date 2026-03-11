<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;

/**
 * @extends RuleTestCase<StateMachineableCannotDuplicateTransitionTriggerPerCase>
 */
class StateMachineableCannotDuplicateTransitionTriggerPerCaseTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new StateMachineableCannotDuplicateTransitionTriggerPerCase;
    }

    #[Test]
    public function it_passes_when_no_duplicate_triggers_per_case(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/ValidStateMachineable.php')], []);
    }

    #[Test]
    public function it_fails_when_a_case_has_duplicate_triggers(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/DuplicateTransitionTrigger.php')], [
            [
                '[Tests\Fixtures\Support\Users\Status\Triggers\Activate] duplicated: A trigger can only be used once per case.',
                25,
            ],
        ]);
    }
}
