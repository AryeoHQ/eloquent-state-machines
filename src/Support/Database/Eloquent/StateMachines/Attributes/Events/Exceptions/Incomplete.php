<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions;

use Illuminate\Support\Stringable;
use LogicException;

class Incomplete extends LogicException
{
    private Stringable $template { get => str('The `%s` event requires a matching `%s` event.'); }

    public function __construct(string $declared, string $missing)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [$declared, $missing])->toString()
        );
    }
}
