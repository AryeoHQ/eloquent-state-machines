<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions;

use Illuminate\Support\Stringable;
use LogicException;

class Reused extends LogicException
{
    private Stringable $template { get => str('The event [%s] is declared on both [%s] and [%s].'); }

    public function __construct(string $event, string $first, string $second)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [$event, $first, $second])->toString()
        );
    }
}
