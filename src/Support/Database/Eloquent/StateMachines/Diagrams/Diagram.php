<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Diagrams;

use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Support\Database\Eloquent\StateMachines\Contracts\StateMachineable;

final class Diagram
{
    /** @var class-string<StateMachineable&BackedEnum> */
    public readonly string $current;

    private null|self $new = null;

    private bool $remove = false;

    public readonly Direction $direction;

    public bool $isMermaidable {
        get => class_exists($this->current) && is_subclass_of($this->current, StateMachineable::class);
    }

    public function __construct(string $class, Direction $direction = Direction::LeftToRight)
    {
        $this->current = $class;
        $this->direction = $direction;
    }

    /**
     * @return Collection<array-key, Diagram>
     */
    public static function extract(Stringable|string $content): Collection
    {
        $content = str($content);
        $blockPattern = '/(diagram:([^:\s]+):start[\s\S]*?diagram:\2:end)/m';  // group1 = whole block, group2 = id
        $directionPattern = '/^\s*direction\s+([A-Za-z-]+)\b/im';

        $diagrams = $content->matchAll($blockPattern)->map(function (string $block) use ($directionPattern) {
            $class = Str::of($block)->match('/^diagram:([^:\s]+):start/m')->toString();
            $direction = Str::of($block)->match($directionPattern)->toString();

            return new self(
                class: $class,
                direction: Direction::tryFrom($direction) ?? Direction::LeftToRight,
                );
                });

            /** @phpstan-ignore return.type (Higher order proxy confuses PHPStan) */
            return $diagrams->unique->current;
    }

    /**
     * @return Collection<array-key, Diagram>
     */
    public static function extractFrom(string $path): Collection
    {
        if (! File::exists($path)) {
            return collect();
        }

        return self::extract(
            File::get($path)
        );
    }

    /**
     * @param  class-string<StateMachineable&BackedEnum>  $class
     */
    public function convertTo(string $class): static
    {
        $this->new = new self(
            class: $class,
            direction: $this->direction,
        );

        return $this;
    }

    public function markForRemoval(): static
    {
        $this->remove = true;

        return $this;
    }

    public function toMermaid(): Stringable
    {
        $rendered = View::make('state-machine::diagram', [
            'direction' => $this->direction->value,
            'stateMachineable' => $this->current,
        ])->render();

        return str($rendered)->rtrim(PHP_EOL);
    }

    public function update(Stringable|string $content): Stringable
    {
        $content = str($content);
        $current = $this->toMermaid();
        $startLine = $current->before(PHP_EOL);
        $endLine = $current->afterLast(PHP_EOL);
        $pattern = '/'.preg_quote($startLine->toString(), '/').'.*?'.preg_quote($endLine->toString(), '/').'/s';

        return $content->replaceMatches(
            $pattern,
            match ($this->remove) {
                true => '',
                false => $this->new ? $this->new->toMermaid() : $current->toString()
            }
        );
    }

    public function updateIn(string $path): static
    {
        File::put(
            $path,
            $this->update(
                File::get($path)
            )->toString(),
        );

        return $this;
    }
}
