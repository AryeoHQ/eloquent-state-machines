<?php

declare(strict_types=1);

namespace Tests\Tooling\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Tooling\Concerns\GetsFixtures;
use Tooling\EloquentStateMachines\PhpStan\Rules\TriggersMustDefineHandle;

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
        $this->analyse([$this->getFixturePath('PhpStan/Triggers/ValidTrigger.php')], []);
    }

    #[Test]
    public function it_fails_when_trigger_does_not_define_handle(): void
    {
        $this->analyse([$this->getFixturePath('PhpStan/Triggers/HandleNotDefined.php')], [
            [
                'Triggers must define a handle() method.',
                11,
            ],
        ]);
    }
}
