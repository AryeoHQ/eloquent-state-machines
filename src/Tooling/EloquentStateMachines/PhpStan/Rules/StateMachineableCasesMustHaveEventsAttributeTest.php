<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;

/**
 * @extends RuleTestCase<StateMachineableCasesMustHaveEventsAttribute>
 */
class StateMachineableCasesMustHaveEventsAttributeTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new StateMachineableCasesMustHaveEventsAttribute;
    }

    #[Test]
    public function it_passes_when_all_cases_have_events_attribute(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/ValidStateMachineable.php')], []);
    }

    #[Test]
    public function it_fails_when_a_case_is_missing_events_attribute(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/MissingEventsAttribute.php')], [
            [
                '#[Events] attribute required on [StateMachineable] cases.',
                20,
            ],
        ]);
    }
}
