<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PhpStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Tooling\Concerns\GetsFixtures;

/**
 * @extends RuleTestCase<TriggerCannotPerformIoInsideTransaction>
 */
class TriggerCannotPerformIoInsideTransactionTest extends RuleTestCase
{
    use GetsFixtures;

    /** @var array<int, string> */
    private array $ioNamespaces = [];

    protected function getRule(): Rule
    {
        return new TriggerCannotPerformIoInsideTransaction($this->ioNamespaces);
    }

    #[Test]
    public function it_fails_when_handle_performs_io(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/TriggerPerformsIo.php')], [
            [$this->message('Illuminate\Support\Facades\Http'), 33],
            [$this->message('Illuminate\Support\Facades\Mail'), 34],
            [$this->message('Illuminate\Http\Client\Factory'), 35],
            [$this->message('Illuminate\Http\Client\PendingRequest'), 36],
            [$this->message('GuzzleHttp\ClientInterface'), 37],
            [$this->message('Psr\Http\Client\ClientInterface'), 38],
            [$this->message('Illuminate\Contracts\Mail\Mailer'), 39],
            [$this->message('Psr\Http\Client\ClientInterface'), 40],
        ]);
    }

    #[Test]
    public function it_fails_when_a_helper_performs_io(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/TriggerPerformsIoInHelper.php')], [
            [$this->message('Illuminate\Support\Facades\Http'), 24],
        ]);
    }

    #[Test]
    public function it_fails_when_an_abstract_parent_performs_io(): void
    {
        $this->analyse([
            $this->getFixturePath('EloquentStateMachines/TriggerInheritsIo.php'),
            $this->getFixturePath('EloquentStateMachines/AbstractTriggerPerformsIo.php'),
        ], [
            [$this->message('Illuminate\Support\Facades\Http'), 14],
        ]);
    }

    #[Test]
    public function it_fails_when_a_trait_of_an_abstract_trigger_performs_io(): void
    {
        $this->analyse([
            $this->getFixturePath('EloquentStateMachines/AbstractTriggerUsesIoTrait.php'),
            $this->getFixturePath('EloquentStateMachines/PerformsIo.php'),
        ], [
            [$this->message('Illuminate\Support\Facades\Http'), 13],
        ]);
    }

    #[Test]
    public function it_passes_when_the_class_is_not_a_trigger(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/NotATriggerPerformsIo.php')], []);
    }

    #[Test]
    public function it_passes_when_the_trigger_is_without_transaction(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/TriggerPerformsIoWithoutTransaction.php')], []);
    }

    #[Test]
    public function it_passes_when_a_parent_is_without_transaction(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/TriggerPerformsIoInheritingWithoutTransaction.php')], []);
    }

    #[Test]
    public function it_fails_when_a_configured_namespace_is_called(): void
    {
        $this->ioNamespaces = ['Tests\Fixtures\Tooling\EloquentStateMachines\ExternalService'];

        $this->analyse([$this->getFixturePath('EloquentStateMachines/TriggerPerformsConfiguredIo.php')], [
            [$this->message('Tests\Fixtures\Tooling\EloquentStateMachines\ExternalService\Client'), 19],
            [$this->message('Tests\Fixtures\Tooling\EloquentStateMachines\ExternalService\Client'), 20],
        ]);
    }

    #[Test]
    public function it_fails_when_a_configured_namespace_differs_in_case(): void
    {
        $this->ioNamespaces = ['tests\fixtures\tooling\eloquentstatemachines\EXTERNALSERVICE'];

        $this->analyse([$this->getFixturePath('EloquentStateMachines/TriggerPerformsConfiguredIo.php')], [
            [$this->message('Tests\Fixtures\Tooling\EloquentStateMachines\ExternalService\Client'), 19],
            [$this->message('Tests\Fixtures\Tooling\EloquentStateMachines\ExternalService\Client'), 20],
        ]);
    }

    #[Test]
    public function it_passes_when_the_namespace_is_not_configured(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/TriggerPerformsConfiguredIo.php')], []);
    }

    #[Test]
    public function it_passes_when_the_trigger_performs_no_io(): void
    {
        $this->analyse([$this->getFixturePath('EloquentStateMachines/TriggerWithoutIo.php')], []);
    }

    private function message(string $class): string
    {
        return "A trigger cannot call `{$class}` inside its lifecycle transaction. Claim the row with a short trigger, make the call after the claim commits, and record the outcome with a second trigger. See README.md.";
    }
}
