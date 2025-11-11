<?php

declare(strict_types=1);

namespace Support\Database\Eloquent\StateMachines\Diagrams;

enum Direction: string
{
    case LeftToRight = 'LR';
    case TopToBottom = 'TB';
    case RightToLeft = 'RL';
    case BottomToTop = 'BT';
}
