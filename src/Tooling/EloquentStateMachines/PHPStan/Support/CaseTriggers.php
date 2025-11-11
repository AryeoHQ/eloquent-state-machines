<?php

declare(strict_types=1);

namespace Tooling\EloquentStateMachines\PHPStan\Support;

class CaseTriggers
{
    /** @var array<int, string> */
    private array $items;

    /**
     * @param  array<int, string>  $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function hasDuplicates(): bool
    {
        return $this->duplicates()->isNotEmpty();
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function duplicates(): \Illuminate\Support\Collection
    {
        return collect($this->items)
            ->duplicates()
            ->unique()
            ->values();
    }
}
