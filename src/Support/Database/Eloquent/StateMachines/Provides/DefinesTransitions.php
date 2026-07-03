<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Provides;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use ReflectionAttribute;
use ReflectionEnumBackedCase;
use Support\Database\Eloquent\StateMachines\Attributes\Transitions\Transition;

trait DefinesTransitions
{
    /**
     * @return Collection<array-key, Transition>
     */
    public function transitions(): Collection
    {
        $reflection = new ReflectionEnumBackedCase($this, $this->name);

        return collect($reflection->getAttributes(Transition::class))->map(
            fn (ReflectionAttribute $attribute): Transition => $attribute->newInstance()
        );
    }

    public function assertDefinesTransitions(self ...$to): static
    {
        Assert::assertEqualsCanonicalizing(
            collect($to)->map(fn (self $case): string => $case->name)->all(),
            $this->transitions()->map(fn (Transition $transition): string => $transition->to->name)->all(),
            "Transition mismatch on [{$this->name}].",
        );

        return $this;
    }

    public function assertIsTerminal(): static
    {
        Assert::assertEmpty(
            $this->transitions(),
            "Expected [{$this->name}] to be a terminal state."
        );

        return $this;
    }
}
