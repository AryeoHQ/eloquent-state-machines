<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<TriggerCannotUseSerializesModels>
 */
class TriggerCannotUseSerializesModelsTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new TriggerCannotUseSerializesModels;
    }

    private function getSourcePath(string $filename): string
    {
        return __DIR__.'/../../../../../src/Support/Database/Eloquent/StateMachines/Triggers/'.$filename;
    }

    #[Test]
    public function it_passes_when_trigger_does_not_use_serializes_models(): void
    {
        $this->analyse([$this->getSourcePath('Trigger.php')], [
            [
                'No error with identifier actions.final is reported on line 21.',
                21,
            ],
            [
                'No error with identifier actions.handle is reported on line 21.',
                21,
            ],
        ]);
    }
}
