<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;

/**
 * @extends RuleTestCase<TransitionUsingMustBeATrigger>
 */
class TransitionUsingMustBeATriggerTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new TransitionUsingMustBeATrigger(
            self::getContainer()->getByType(\PHPStan\Reflection\ReflectionProvider::class)
        );
    }

    #[Test]
    public function it_passes_when_transition_using_is_a_trigger(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/ValidStateMachineable.php')], []);
    }

    #[Test]
    public function it_fails_when_transition_using_is_not_a_trigger(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/TransitionUsingNotATrigger.php')], [
            [
                '[Tests\Fixtures\Tooling\EloquentStateMachines\NotATrigger] is not a Trigger. The #[Transition] using parameter must reference a class that extends Trigger.',
                20,
            ],
        ]);
    }
}
