<?php

declare(strict_types=1);

namespace Tests\Tooling\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Tooling\Concerns\GetsFixtures;
use Tooling\EloquentStateMachines\PhpStan\Rules\StateMachineableCannotDuplicateTransitionTriggerPerCase;

/**
 * @extends RuleTestCase<StateMachineableCannotDuplicateTransitionTriggerPerCase>
 */
class StateMachineableCannotDuplicateTransitionTriggerPerCaseTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new StateMachineableCannotDuplicateTransitionTriggerPerCase(
            self::getContainer()->getByType(\PHPStan\Reflection\ReflectionProvider::class)
        );
    }

    #[Test]
    public function it_passes_when_no_duplicate_triggers_per_case(): void
    {
        $this->analyse([$this->getFixturePath('PhpStan/StateMachines/ValidStateMachineable.php')], []);
    }

    #[Test]
    public function it_fails_when_a_case_has_duplicate_triggers(): void
    {
        $this->analyse([$this->getFixturePath('PhpStan/StateMachines/DuplicateTransitionTrigger.php')], [
            [
                '[Tests\Fixtures\Support\Users\Status\Triggers\Activate] duplicated: A trigger can only be used once per case.',
                25,
            ],
        ]);
    }
}
