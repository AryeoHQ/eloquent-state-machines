<?php

declare(strict_types=1);

namespace Tests\Fixtures\Support\Users;

class SecondaryUser extends User
{
    protected $connection = 'secondary';

    protected $table = 'users';
}
