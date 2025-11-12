<?php

declare(strict_types=1);

namespace Tests\Fixtures\Users\Status\Triggers\Exceptions;

class Unprocessable extends \Exception
{
    public function __construct()
    {
        parent::__construct('The trigger could not be processed.');
    }
}
