<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Diagrams;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Support\Users\Status\Status;
use Tests\Fixtures\Tooling\EloquentStateMachines\MissingEventsAttribute;
use Tests\TestCase;

class DiagramTest extends TestCase
{
    #[Test]
    public function can_extract(): void
    {
        $content = <<<'MD'
        <!-- diagram:one:start -->
        ```mermaid
        stateDiagram-v2
            direction LR
        ```
        <!-- diagram:one:end -->
        <!-- diagram:two:start -->
        ```mermaid
        stateDiagram-v2
            direction TB
        <!-- diagram:two:end -->
        MD;
        $diagrams = Diagram::extract($content);

        $this->assertContainsOnlyInstancesOf(Diagram::class, $diagrams);
        $this->assertCount(2, $diagrams);

        tap(
            $diagrams->first(),
            function (Diagram $diagram) {
                $this->assertSame('one', $diagram->current);
                $this->assertSame(Direction::LeftToRight, $diagram->direction);
            }
        );

        tap(
            $diagrams->last(),
            function (Diagram $diagram) {
                $this->assertSame('two', $diagram->current);
                $this->assertSame(Direction::TopToBottom, $diagram->direction);
            }
        );
    }

    #[Test]
    public function can_extract_from(): void
    {
        $path = '/tmp/diagram.md';
        $content = <<<'MD'
        <!-- diagram:Tests\Fixtures\Support\Users\Status\Status:start -->
        ```mermaid
        stateDiagram-v2
            direction LR
        ```
        <!-- diagram:Tests\Fixtures\Support\Users\Status\Status:end -->
        MD;

        File::expects('exists')->with($path)->andReturnTrue();
        File::expects('get')->with($path)->andReturn($content);

        $diagrams = Diagram::extractFrom($path);

        $this->assertContainsOnlyInstancesOf(Diagram::class, $diagrams);
        $this->assertCount(1, $diagrams);

        tap(
            $diagrams->first(),
            function (Diagram $diagram) {
                $this->assertSame(Status::class, $diagram->current);
                $this->assertSame(Direction::LeftToRight, $diagram->direction);
            }
        );
    }

    #[Test]
    public function can_gracefully_handle_missing_file(): void
    {
        $path = '/tmp/non-existent-diagram.md';

        File::expects('exists')->with($path)->andReturnFalse();

        $diagrams = Diagram::extractFrom($path);

        $this->assertTrue($diagrams->isEmpty());
    }

    #[Test]
    public function can_update(): void
    {
        $current = <<<'MD'
        <!-- diagram:Tests\Fixtures\Support\Users\Status\Status:start -->
        <!-- diagram:Tests\Fixtures\Support\Users\Status\Status:end -->
        MD;

        $updated = new Diagram(Status::class, Direction::LeftToRight)->update($current)->toString();

        $this->assertStringContainsString('<!-- diagram:Tests\Fixtures\Support\Users\Status\Status:start -->', $updated);
        $this->assertStringContainsString('<!-- diagram:Tests\Fixtures\Support\Users\Status\Status:end -->', $updated);
        $this->assertStringContainsString('**`Tests\Fixtures\Support\Users\Status\Status`**', $updated);
        $this->assertStringContainsString('```mermaid', $updated);
        $this->assertStringContainsString('stateDiagram-v2', $updated);
        $this->assertStringContainsString('direction LR', $updated);
        $this->assertStringContainsString('[*] --> Registered', $updated);
        $this->assertStringContainsString('Registered --> Activated: activate()', $updated);
        $this->assertStringContainsString('Registered --> Suspended: suspend()', $updated);
        $this->assertStringContainsString('Activated --> Deactivated: deactivate()', $updated);
    }

    #[Test]
    public function update_only_modifies_when_matched(): void
    {
        $current = <<<'MD'
        <!-- diagram:one:start -->
        <!-- diagram:one:end -->
        MD;

        $updated = (new Diagram(Status::class, Direction::LeftToRight))->update($current)->toString();

        $this->assertSame($current, $updated);
    }

    #[Test]
    public function can_update_to_converted(): void
    {
        $current = <<<'MD'
        <!-- diagram:Tests\Fixtures\Support\Users\Status\Status:start -->
        <!-- diagram:Tests\Fixtures\Support\Users\Status\Status:end -->
        MD;

        $updated = new Diagram(Status::class, Direction::LeftToRight)->convertTo(MissingEventsAttribute::class)->update($current)->toString();

        $this->assertStringNotContainsString(Status::class, $updated);
        $this->assertStringContainsString('<!-- diagram:'.MissingEventsAttribute::class.':start -->', $updated);
        $this->assertStringContainsString('<!-- diagram:'.MissingEventsAttribute::class.':end -->', $updated);
        $this->assertStringContainsString('**`'.MissingEventsAttribute::class.'`**', $updated);
        $this->assertStringContainsString('```mermaid', $updated);
        $this->assertStringContainsString('stateDiagram-v2', $updated);
        $this->assertStringContainsString('direction LR', $updated);
        $this->assertStringContainsString('[*] --> Active', $updated);
        $this->assertStringNotContainsString('Active -->', $updated);
    }

    #[Test]
    public function can_be_removed(): void
    {
        $current = <<<'MD'
        <!-- diagram:Tests\Fixtures\Support\Users\Status\Status:start -->
        <!-- diagram:Tests\Fixtures\Support\Users\Status\Status:end -->
        MD;

        $updated = (new Diagram(Status::class, Direction::LeftToRight))->markForRemoval()->update($current)->toString();

        $this->assertStringNotContainsString(Status::class, $updated);
        $this->assertStringNotContainsString('<!-- diagram:'.Status::class.':start -->', $updated);
        $this->assertStringNotContainsString('<!-- diagram:'.Status::class.':end -->', $updated);
        $this->assertStringNotContainsString('**`'.Status::class.'`**', $updated);
        $this->assertStringNotContainsString('```mermaid', $updated);
        $this->assertStringNotContainsString('stateDiagram-v2', $updated);
        $this->assertStringNotContainsString('direction LR', $updated);
        $this->assertStringNotContainsString('[*] --> Registered', $updated);
    }

    #[Test]
    public function can_update_in(): void
    {
        $path = '/tmp/diagram.md';
        $stateMachine = Status::class;
        $content = <<<MD
        <!-- diagram:$stateMachine:start -->
        <!-- diagram:$stateMachine:end -->
        MD;

        $expected = new Diagram($stateMachine, Direction::LeftToRight)->update($content)->toString();

        File::expects('get')->with($path)->andReturn($content);
        File::expects('put')->with($path, $expected)->andReturnTrue();

        new Diagram($stateMachine, Direction::LeftToRight)->updateIn($path);
    }
}
