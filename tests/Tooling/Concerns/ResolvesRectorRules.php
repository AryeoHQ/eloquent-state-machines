<?php

declare(strict_types=1);

namespace Tests\Tooling\Concerns;

use Rector\Config\RectorConfig;
use Rector\DependencyInjection\LazyContainerFactory;
use Tooling\Rector\Rules\Rule;

// TODO: We need to understand this better
// TODO: Then move it to tooling for broader consumption `Support\Tooling\Rector\Testing\Concerns`
trait ResolvesRectorRules
{
    private RectorConfig $rectorConfig;

    protected function setUpResolvesRectorRules(): void
    {
        $this->rectorConfig = (new LazyContainerFactory)->create();
        $this->rectorConfig->boot();
    }

    /**
     * @template T of Rule
     *
     * @param  class-string<T>  $class
     * @return T
     */
    protected function resolveRule(string $class): Rule
    {
        if (! isset($this->rectorConfig)) {
            $this->setUpResolvesRectorRules();
        }

        return $this->rectorConfig->make($class);
    }
}
