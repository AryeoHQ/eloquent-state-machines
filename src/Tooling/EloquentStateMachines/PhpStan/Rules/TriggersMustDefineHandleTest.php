<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;

/**
 * @extends RuleTestCase<TriggersMustDefineHandle>
 */
class TriggersMustDefineHandleTest extends RuleTestCase
{
    use GetsFixtures;

    protected function getRule(): Rule
    {
        return new TriggersMustDefineHandle;
    }

    #[Test]
    public function it_passes_when_trigger_defines_handle(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/ValidTrigger.php')], []);
    }

    #[Test]
    public function it_fails_when_trigger_does_not_define_handle(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/HandleNotDefined.php')], [
            [
                'Triggers must define a handle() method.',
                11,
            ],
        ]);
    }
}
