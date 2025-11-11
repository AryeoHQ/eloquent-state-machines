<?php

declare(strict_types=1);

namespace Tests\Support\Database\Eloquent\StateMachines\Diagrams;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Support\Database\Eloquent\StateMachines\Diagrams\Diagram;
use Support\Database\Eloquent\StateMachines\Diagrams\Direction;
use Tests\Fixtures\Users\Status\EventsNotDefined;
use Tests\Fixtures\Users\Status\Status;
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
        <!-- diagram:Tests\Fixtures\Users\Status\Status:start -->
        ```mermaid
        stateDiagram-v2
            direction LR
        ```
        <!-- diagram:Tests\Fixtures\Users\Status\Status:end -->
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
        <!-- diagram:Tests\Fixtures\Users\Status\Status:start -->
        <!-- diagram:Tests\Fixtures\Users\Status\Status:end -->
        MD;

        $updated = new Diagram(Status::class, Direction::LeftToRight)->update($current)->toString();

        $this->assertStringContainsString('<!-- diagram:Tests\Fixtures\Users\Status\Status:start -->', $updated);
        $this->assertStringContainsString('<!-- diagram:Tests\Fixtures\Users\Status\Status:end -->', $updated);
        $this->assertStringContainsString('**`Tests\Fixtures\Users\Status\Status`**', $updated);
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
        <!-- diagram:Tests\Fixtures\Users\Status\Status:start -->
        <!-- diagram:Tests\Fixtures\Users\Status\Status:end -->
        MD;

        $updated = new Diagram(Status::class, Direction::LeftToRight)->convertTo(EventsNotDefined::class)->update($current)->toString();

        $this->assertStringNotContainsString('Tests\Fixtures\Users\Status\Status', $updated);
        $this->assertStringContainsString('<!-- diagram:Tests\Fixtures\Users\Status\EventsNotDefined:start -->', $updated);
        $this->assertStringContainsString('<!-- diagram:Tests\Fixtures\Users\Status\EventsNotDefined:end -->', $updated);
        $this->assertStringContainsString('**`Tests\Fixtures\Users\Status\EventsNotDefined`**', $updated);
        $this->assertStringContainsString('```mermaid', $updated);
        $this->assertStringContainsString('stateDiagram-v2', $updated);
        $this->assertStringContainsString('direction LR', $updated);
        $this->assertStringContainsString('[*] --> Pending', $updated);
        $this->assertStringNotContainsString('Pending -->', $updated);
    }

    #[Test]
    public function can_be_removed(): void
    {
        $current = <<<'MD'
        <!-- diagram:Tests\Fixtures\Users\Status\Status:start -->
        <!-- diagram:Tests\Fixtures\Users\Status\Status:end -->
        MD;

        $updated = (new Diagram(Status::class, Direction::LeftToRight))->markForRemoval()->update($current)->toString();

        $this->assertStringNotContainsString('Tests\Fixtures\Users\Status\Status', $updated);
        $this->assertStringNotContainsString('<!-- diagram:Tests\Fixtures\Users\Status\Status:start -->', $updated);
        $this->assertStringNotContainsString('<!-- diagram:Tests\Fixtures\Users\Status\Status:end -->', $updated);
        $this->assertStringNotContainsString('**`Tests\Fixtures\Users\Status\Status`**', $updated);
        $this->assertStringNotContainsString('```mermaid', $updated);
        $this->assertStringNotContainsString('stateDiagram-v2', $updated);
        $this->assertStringNotContainsString('direction LR', $updated);
        $this->assertStringNotContainsString('[*] --> Registered', $updated);
    }

    #[Test]
    public function can_update_in(): void
    {
        $path = '/tmp/diagram.md';
        $content = <<<'MD'
        <!-- diagram:Tests\Fixtures\Users\Status\Status:start -->
        <!-- diagram:Tests\Fixtures\Users\Status\Status:end -->
        MD;

        $expected = new Diagram(Status::class, Direction::LeftToRight)->update($content)->toString();

        File::expects('get')->with($path)->andReturn($content);
        File::expects('put')->with($path, $expected)->andReturnTrue();

        new Diagram(Status::class, Direction::LeftToRight)->updateIn($path);
    }
}
