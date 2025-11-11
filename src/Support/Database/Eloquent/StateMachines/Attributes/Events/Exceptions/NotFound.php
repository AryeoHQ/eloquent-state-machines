<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Attributes\Events\Exceptions;

use Illuminate\Support\Stringable;
use LogicException;

class NotFound extends LogicException
{
    private Stringable $template { get => str('The `%s` event [%s] was not found.'); }

    public function __construct(string $key, string $name)
    {
        parent::__construct(
            $this->template->replaceArray('%s', [$key, $name])->toString()
        );
    }
}
