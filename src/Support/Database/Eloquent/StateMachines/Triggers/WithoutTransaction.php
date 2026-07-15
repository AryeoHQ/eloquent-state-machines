<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Triggers;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class WithoutTransaction {}
